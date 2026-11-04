<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationsTesterCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('firebase_access_token');
    }

    private function configurerFirebase(): void
    {
        $cle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($cle, $clePriveePem);

        config([
            'services.firebase.project_id' => 'afrolia-test',
            'services.firebase.credentials_json' => json_encode([
                'client_email' => 'test@afrolia-test.iam.gserviceaccount.com',
                'private_key' => $clePriveePem,
            ]),
        ]);
    }

    private function creerUtilisateur(string $phone, string $email): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('motdepasse'),
            'role' => 'user',
        ]);
    }

    public function test_echoue_proprement_si_firebase_n_est_pas_configure(): void
    {
        config(['services.firebase.project_id' => null, 'services.firebase.credentials_json' => null]);
        $this->creerUtilisateur('0700000001', 'test@afrolia.app');

        $this->artisan('notifications:tester', ['identifiant' => '0700000001'])
            ->assertFailed()
            ->expectsOutputToContain('rien à tester');
    }

    public function test_echoue_proprement_si_le_compte_est_introuvable(): void
    {
        $this->configurerFirebase();

        $this->artisan('notifications:tester', ['identifiant' => '0799999999'])
            ->assertFailed()
            ->expectsOutputToContain('Aucun compte trouvé');
    }

    public function test_trouve_le_compte_par_email_ou_par_telephone(): void
    {
        $this->configurerFirebase();
        $this->creerUtilisateur('0700000001', 'test@afrolia.app');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
        ]);

        $this->artisan('notifications:tester', ['identifiant' => 'test@afrolia.app'])
            ->expectsOutputToContain('Compte trouvé')
            ->expectsOutputToContain("n'a aucun appareil enregistré");
    }

    public function test_echoue_proprement_si_aucun_appareil_n_est_enregistre(): void
    {
        $this->configurerFirebase();
        $this->creerUtilisateur('0700000001', 'test@afrolia.app');

        $this->artisan('notifications:tester', ['identifiant' => '0700000001'])
            ->assertFailed()
            ->expectsOutputToContain("n'a aucun appareil enregistré");
    }

    public function test_envoie_reellement_une_notification_de_test_et_reussit(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateur('0700000001', 'test@afrolia.app');
        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => 'jeton-appareil',
            'plateforme' => 'android',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200),
        ]);

        $this->artisan('notifications:tester', ['identifiant' => '0700000001'])
            ->assertSuccessful()
            ->expectsOutputToContain('Envoyée à un appareil android');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && $request['message']['token'] === 'jeton-appareil'
                && $request['message']['notification']['title'] === 'Test Afrolia';
        });
    }

    public function test_echoue_proprement_si_la_cle_firebase_est_invalide(): void
    {
        config([
            'services.firebase.project_id' => 'afrolia-test',
            'services.firebase.credentials_json' => 'pas-du-json-valide',
        ]);
        $utilisateur = $this->creerUtilisateur('0700000001', 'test@afrolia.app');
        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => 'jeton-appareil',
            'plateforme' => 'android',
        ]);

        $this->artisan('notifications:tester', ['identifiant' => '0700000001'])
            ->assertFailed()
            ->expectsOutputToContain('jeton d\'accès Google');
    }

    public function test_signale_un_echec_d_envoi_sans_faire_planter_la_commande(): void
    {
        $this->configurerFirebase();
        $utilisateur = $this->creerUtilisateur('0700000001', 'test@afrolia.app');
        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => 'jeton-perime',
            'plateforme' => 'ios',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-oauth-test'], 200),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        $this->artisan('notifications:tester', ['identifiant' => '0700000001'])
            ->assertFailed()
            ->expectsOutputToContain('Échec sur un appareil ios');

        $this->assertDatabaseMissing('device_tokens', ['token' => 'jeton-perime']);
    }
}
