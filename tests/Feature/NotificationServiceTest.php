<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\UsersApp;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Le jeton d'accès est mis en cache par clé fixe : jamais de fuite
        // d'un test à l'autre (le store "array" des tests persiste pour
        // tout le process PHPUnit, pas seulement le test en cours).
        Cache::forget('firebase_access_token');
    }

    private function configurerFirebase(): array
    {
        $cle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($cle, $clePriveePem);

        $identifiants = [
            'client_email' => 'test@afrolia-test.iam.gserviceaccount.com',
            'private_key' => $clePriveePem,
        ];

        config([
            'services.firebase.project_id' => 'afrolia-test',
            'services.firebase.credentials_json' => json_encode($identifiants),
        ]);

        return $identifiants;
    }

    private function creerUtilisateurAvecJeton(string $phone, string $token): UsersApp
    {
        $utilisateur = UsersApp::create([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => Hash::make('motdepasse'),
            'role' => 'user',
        ]);

        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => $token,
            'plateforme' => 'android',
        ]);

        return $utilisateur;
    }

    public function test_ignore_silencieusement_si_firebase_n_est_pas_configure(): void
    {
        config(['services.firebase.project_id' => null, 'services.firebase.credentials_json' => null]);
        Http::fake();

        $utilisateur = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-abc');

        (new NotificationService())->envoyerAUtilisateur($utilisateur, 'Titre', 'Corps');

        Http::assertNothingSent();
    }

    public function test_ignore_silencieusement_si_utilisateur_est_null(): void
    {
        $this->configurerFirebase();
        Http::fake();

        (new NotificationService())->envoyerAUtilisateur(null, 'Titre', 'Corps');

        Http::assertNothingSent();
    }

    public function test_envoie_la_notification_a_l_appareil_enregistre(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-abc');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/afrolia-test/messages/0'], 200),
        ]);

        (new NotificationService())->envoyerAUtilisateur(
            $utilisateur,
            'Nouvelle réservation',
            'Vous avez une nouvelle réservation',
            ['type' => 'reservation', 'id' => 42],
        );

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && $request->hasHeader('Authorization', 'Bearer jeton-oauth-test')
                && $request['message']['token'] === 'jeton-abc'
                && $request['message']['notification']['title'] === 'Nouvelle réservation'
                && $request['message']['data']['id'] === '42';
        });
    }

    public function test_envoie_a_chaque_appareil_enregistre_de_l_utilisateur(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-telephone-1');
        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => 'jeton-telephone-2',
            'plateforme' => 'ios',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200),
        ]);

        (new NotificationService())->envoyerAUtilisateur($utilisateur, 'Titre', 'Corps');

        Http::assertSentCount(3); // 1 échange OAuth2 + 2 appareils
    }

    public function test_ne_redemande_pas_de_jeton_oauth2_pour_un_deuxieme_envoi(): void
    {
        $this->configurerFirebase();
        $utilisateurA = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-a');
        $utilisateurB = $this->creerUtilisateurAvecJeton('0700000002', 'jeton-b');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200),
        ]);

        $service = new NotificationService();
        $service->envoyerAUtilisateur($utilisateurA, 'Titre', 'Corps');
        $service->envoyerAUtilisateur($utilisateurB, 'Titre', 'Corps');

        Http::assertSentCount(3); // 1 seul échange OAuth2 (mis en cache) + 2 envois FCM
    }

    public function test_oublie_un_jeton_d_appareil_desinstalle(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-perime');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        (new NotificationService())->envoyerAUtilisateur($utilisateur, 'Titre', 'Corps');

        $this->assertDatabaseMissing('device_tokens', ['token' => 'jeton-perime']);
    }

    public function test_conserve_un_jeton_d_appareil_en_cas_d_erreur_temporaire(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateurAvecJeton('0700000001', 'jeton-valide');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);

        (new NotificationService())->envoyerAUtilisateur($utilisateur, 'Titre', 'Corps');

        // Une panne temporaire du côté de Firebase ne doit pas faire perdre
        // un jeton par ailleurs valide.
        $this->assertDatabaseHas('device_tokens', ['token' => 'jeton-valide']);
    }
}
