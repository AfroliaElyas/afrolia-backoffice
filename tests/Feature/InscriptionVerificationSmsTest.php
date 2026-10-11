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
}
