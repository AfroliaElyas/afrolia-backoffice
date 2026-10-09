<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Avant cette correction, demanderOtpReset() générait et enregistrait un
 * code OTP puis répondait "Code OTP envoyé avec succès" même si aucun SMS
 * n'était réellement transmis (le code n'était que journalisé) : une
 * utilisatrice ayant oublié son mot de passe n'avait alors aucun moyen de
 * recevoir le code.
 */
class OtpSmsTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test', 'last_name' => 'Test', 'phone' => '0700000050',
            'password' => Hash::make('motdepasse'), 'role' => 'user',
        ]);
    }

    public function test_sans_fournisseur_sms_reel_la_demande_est_refusee_explicitement(): void
    {
        $utilisateur = $this->creerUtilisateur();

        $response = $this->postJson('/api/otp', ['phone' => $utilisateur->phone]);

        $response->assertStatus(503);
        $this->assertNull($utilisateur->fresh()->otp);
    }

    public function test_avec_un_fournisseur_sms_fonctionnel_le_code_est_genere_et_enregistre(): void
    {
        $faux = new class implements SmsGatewayInterface {
            public array $messagesEnvoyes = [];

            public function envoyer(string $telephone, string $message): bool
            {
                $this->messagesEnvoyes[] = [$telephone, $message];

                return true;
            }
        };
        $this->app->instance(SmsGatewayInterface::class, $faux);

        $utilisateur = $this->creerUtilisateur();

        $response = $this->postJson('/api/otp', ['phone' => $utilisateur->phone]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotNull($utilisateur->fresh()->otp);
        $this->assertCount(1, $faux->messagesEnvoyes);
        $this->assertSame($utilisateur->phone, $faux->messagesEnvoyes[0][0]);
    }

    public function test_si_l_envoi_echoue_aucun_code_n_est_enregistre(): void
    {
        $faux = new class implements SmsGatewayInterface {
            public function envoyer(string $telephone, string $message): bool
            {
                return false;
            }
        };
        $this->app->instance(SmsGatewayInterface::class, $faux);

        $utilisateur = $this->creerUtilisateur();

        $response = $this->postJson('/api/otp', ['phone' => $utilisateur->phone]);

        $response->assertStatus(503);
        $this->assertNull($utilisateur->fresh()->otp);
    }
}
