<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avant cette fonctionnalité, register() créait le compte et délivrait un
 * jeton immédiatement, sans aucune vérification du numéro de téléphone.
 */
class InscriptionVerificationSmsTest extends TestCase
{
    use RefreshDatabase;

    private function corpsInscription(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Aïcha', 'prenom' => 'Koné', 'phone' => '0700000070',
            'email' => null, 'password' => 'motdepasse', 'role' => 'user',
        ], $overrides);
    }

    private function fauxConnecteurFonctionnel(): SmsGatewayInterface
    {
        return new class implements SmsGatewayInterface {
            public array $messagesEnvoyes = [];

            public function envoyer(string $telephone, string $message): bool
            {
                $this->messagesEnvoyes[] = [$telephone, $message];

                return true;
            }
        };
    }

    public function test_sans_fournisseur_sms_reel_l_inscription_est_refusee_explicitement(): void
    {
        $response = $this->postJson('/api/register', $this->corpsInscription());

        $response->assertStatus(503);
        $this->assertDatabaseMissing('users_app', ['phone' => '0700000070']);
    }

    public function test_avec_un_fournisseur_sms_fonctionnel_le_compte_est_cree_non_verifie_et_sans_jeton(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);

        $response = $this->postJson('/api/register', $this->corpsInscription());

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertArrayNotHasKey('token', $response->json('data') ?? []);

        $utilisateur = UsersApp::where('phone', '0700000070')->first();
        $this->assertNotNull($utilisateur);
        $this->assertNull($utilisateur->phone_verified_at);
        $this->assertNotNull($utilisateur->otp);
        $this->assertCount(1, $faux->messagesEnvoyes);
    }

    public function test_si_l_envoi_du_sms_echoue_aucun_compte_n_est_laisse_en_place(): void
    {
        $faux = new class implements SmsGatewayInterface {
            public function envoyer(string $telephone, string $message): bool
            {
                return false;
            }
        };
        $this->app->instance(SmsGatewayInterface::class, $faux);

        $response = $this->postJson('/api/register', $this->corpsInscription());

        $response->assertStatus(503);
        $this->assertDatabaseMissing('users_app', ['phone' => '0700000070']);
    }

    public function test_verifier_inscription_avec_le_bon_code_active_le_compte_et_delivre_un_jeton(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);
        $this->postJson('/api/register', $this->corpsInscription());

        $utilisateur = UsersApp::where('phone', '0700000070')->first();
        $otp = $utilisateur->otp;

        $response = $this->postJson('/api/verify-register', ['phone' => '0700000070', 'otp' => $otp]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotNull($response->json('data.token'));

        $utilisateur->refresh();
        $this->assertNotNull($utilisateur->phone_verified_at);
        $this->assertNull($utilisateur->otp);
    }

    public function test_verifier_inscription_avec_un_mauvais_code_echoue(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);
        $this->postJson('/api/register', $this->corpsInscription());

        $response = $this->postJson('/api/verify-register', ['phone' => '0700000070', 'otp' => '000000']);

        $response->assertStatus(401);
        $this->assertNull(UsersApp::where('phone', '0700000070')->first()->phone_verified_at);
    }

    public function test_un_compte_non_verifie_ne_peut_pas_se_connecter(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);
        $this->postJson('/api/register', $this->corpsInscription());

        $response = $this->postJson('/api/login', ['login' => '0700000070', 'password' => 'motdepasse']);

        $response->assertStatus(403);
    }

    public function test_un_compte_verifie_peut_se_connecter_normalement(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);
        $this->postJson('/api/register', $this->corpsInscription());
        $otp = UsersApp::where('phone', '0700000070')->first()->otp;
        $this->postJson('/api/verify-register', ['phone' => '0700000070', 'otp' => $otp]);

        $response = $this->postJson('/api/login', ['login' => '0700000070', 'password' => 'motdepasse']);

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_le_code_du_mot_de_passe_oublie_ne_peut_pas_servir_a_verifier_une_inscription(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);

        // Compte déjà vérifié (inscription terminée depuis longtemps).
        $utilisateur = UsersApp::create([
            'name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '0700000070',
            'password' => bcrypt('motdepasse'), 'role' => 'user', 'phone_verified_at' => now(),
        ]);

        // Quelqu'un (l'utilisatrice elle-même ou un attaquant lisant le SMS)
        // déclenche un code "mot de passe oublié" sur ce compte déjà vérifié.
        $this->postJson('/api/otp', ['phone' => '0700000070']);
        $otpOublie = $utilisateur->fresh()->otp;
        $this->assertNotNull($otpOublie);

        // Ce code ne doit pas pouvoir délivrer un jeton via verify-register.
        $response = $this->postJson('/api/verify-register', ['phone' => '0700000070', 'otp' => $otpOublie]);

        $response->assertStatus(401);
    }

    public function test_une_inscription_abandonnee_peut_etre_recommencee_avec_le_meme_numero(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);

        // Première tentative : code jamais saisi (ex. expiré).
        $this->postJson('/api/register', $this->corpsInscription())->assertStatus(200);
        $this->assertDatabaseCount('users_app', 1);

        // Nouvelle tentative avec le même numéro : doit réussir, pas
        // "numéro déjà utilisé", et remplacer la tentative abandonnée.
        $response = $this->postJson('/api/register', $this->corpsInscription(['prenom' => 'Awa']));

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseCount('users_app', 1);
        $this->assertSame('Awa', UsersApp::where('phone', '0700000070')->first()->last_name);
    }

    public function test_une_inscription_deja_verifiee_ne_peut_pas_etre_recreee(): void
    {
        $faux = $this->fauxConnecteurFonctionnel();
        $this->app->instance(SmsGatewayInterface::class, $faux);
        UsersApp::create([
            'name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '0700000070',
            'password' => bcrypt('motdepasse'), 'role' => 'user', 'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/register', $this->corpsInscription());

        $response->assertStatus(422);
        $this->assertDatabaseCount('users_app', 1);
    }
}
