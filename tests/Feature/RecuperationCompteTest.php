<?php

namespace Tests\Feature;

use App\Mail\LienReinitialisationMotDePasse;
use App\Mail\LienVerificationEmail;
use App\Models\ActionToken;
use App\Models\RecoveryCode;
use App\Models\UsersApp;
use App\Services\RecuperationCompteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vérification d'e-mail, réinitialisation de mot de passe par lien à usage
 * unique (parcours 3), et codes de secours pour récupérer l'accès sans SMS
 * ni e-mail (parcours 4) — la possession d'un code de secours valide est la
 * seule preuve acceptée ; à défaut, rien n'est réinitialisé.
 */
class RecuperationCompteTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(array $overrides = []): UsersApp
    {
        return UsersApp::create(array_merge([
            'name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '07' . random_int(10000000, 99999999),
            'password' => Hash::make('ancienmotdepasse'), 'role' => 'user',
        ], $overrides));
    }

    // --- Inscription : l'e-mail reste facultatif et ne bloque jamais ---

    public function test_inscription_avec_email_et_fournisseur_reel_envoie_la_verification(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'nom' => 'Koné', 'prenom' => 'Aïcha', 'phone' => '0711111111',
            'email' => 'aicha@example.com', 'password' => 'motdepasse', 'role' => 'user',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.emailVerificationEnvoyee', true);
        Mail::assertSent(LienVerificationEmail::class);
        $this->assertNull(UsersApp::where('phone', '0711111111')->first()->email_verified_at);
    }

    public function test_inscription_avec_email_sans_fournisseur_reel_reussit_quand_meme(): void
    {
        config(['mail.default' => 'log']);

        $response = $this->postJson('/api/register', [
            'nom' => 'Koné', 'prenom' => 'Aïcha', 'phone' => '0711111112',
            'email' => 'aicha2@example.com', 'password' => 'motdepasse', 'role' => 'user',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.emailVerificationEnvoyee', false);
        $this->assertNotNull(UsersApp::where('phone', '0711111112')->first());
    }

    public function test_inscription_sans_email_fonctionne_normalement(): void
    {
        $response = $this->postJson('/api/register', [
            'nom' => 'Koné', 'prenom' => 'Aïcha', 'phone' => '0711111113',
            'password' => 'motdepasse', 'role' => 'user',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.emailVerificationEnvoyee', false);
    }

    // --- Ajout / vérification de l'e-mail depuis le profil ---

    public function test_une_personne_connectee_peut_demander_la_verification_de_son_email(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $response = $this->postJson('/api/email/verifier/demander', ['email' => 'nouvelle@example.com']);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame('nouvelle@example.com', $utilisateur->fresh()->email);
        $this->assertNull($utilisateur->fresh()->email_verified_at);
        Mail::assertSent(LienVerificationEmail::class);
    }

    public function test_la_demande_de_verification_refuse_une_adresse_deja_utilisee(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->creerUtilisateur(['email' => 'prise@example.com']);
        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $response = $this->postJson('/api/email/verifier/demander', ['email' => 'prise@example.com']);

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_cliquer_le_lien_de_verification_marque_lemail_verifie(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'test@example.com']);
        $tokenBrut = 'jeton-de-test-1234567890';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'verification_email',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addHours(24),
        ]);

        $response = $this->get("/verifier-email/{$tokenBrut}");

        $response->assertStatus(200)->assertSee('confirmée');
        $this->assertNotNull($utilisateur->fresh()->email_verified_at);
    }

    public function test_un_lien_de_verification_deja_utilise_echoue(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'test@example.com']);
        $tokenBrut = 'jeton-deja-utilise';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'verification_email',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addHours(24),
            'utilise_le' => now(),
        ]);

        $response = $this->get("/verifier-email/{$tokenBrut}");

        $response->assertStatus(200)->assertSee('invalide');
    }

    public function test_un_lien_de_verification_expire_echoue(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'test@example.com']);
        $tokenBrut = 'jeton-expire';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'verification_email',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->subHour(),
        ]);

        $response = $this->get("/verifier-email/{$tokenBrut}");

        $response->assertStatus(200)->assertSee('invalide');
        $this->assertNull($utilisateur->fresh()->email_verified_at);
    }

    // --- Parcours 3 : réinitialisation par lien envoyé par e-mail ---

    public function test_demande_de_reset_par_email_refusee_si_email_non_verifie(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->creerUtilisateur(['email' => 'nonverifie@example.com', 'email_verified_at' => null]);

        $response = $this->postJson('/api/mot-de-passe/email/demander', ['email' => 'nonverifie@example.com']);

        $response->assertStatus(409);
        Mail::assertNothingSent();
    }

    public function test_demande_de_reset_par_email_fonctionne_si_email_verifie(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->creerUtilisateur(['email' => 'verifie@example.com', 'email_verified_at' => now()]);

        $response = $this->postJson('/api/mot-de-passe/email/demander', ['email' => 'verifie@example.com']);

        $response->assertStatus(200);
        Mail::assertSent(LienReinitialisationMotDePasse::class);
    }

    public function test_le_formulaire_de_reset_fonctionne_et_revoque_les_jetons(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'verifie@example.com', 'email_verified_at' => now()]);
        $utilisateur->createToken('mobile');
        $this->assertSame(1, $utilisateur->tokens()->count());

        $tokenBrut = 'jeton-reset-valide';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'reinitialisation_mot_de_passe',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addMinutes(60),
        ]);

        $response = $this->post("/reinitialiser-mot-de-passe/{$tokenBrut}", [
            'mot_de_passe' => 'nouveaumotdepasse',
            'mot_de_passe_confirmation' => 'nouveaumotdepasse',
        ]);

        $response->assertStatus(200)->assertSee('mis à jour');
        $utilisateur->refresh();
        $this->assertTrue(Hash::check('nouveaumotdepasse', $utilisateur->password));
        $this->assertSame(0, $utilisateur->tokens()->count());
    }

    public function test_le_formulaire_de_reset_refuse_des_mots_de_passe_qui_ne_correspondent_pas(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'verifie@example.com', 'email_verified_at' => now()]);
        $tokenBrut = 'jeton-reset-mismatch';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'reinitialisation_mot_de_passe',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addMinutes(60),
        ]);

        $response = $this->post("/reinitialiser-mot-de-passe/{$tokenBrut}", [
            'mot_de_passe' => 'nouveaumotdepasse',
            'mot_de_passe_confirmation' => 'autrechose',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('ancienmotdepasse', $utilisateur->fresh()->password));
    }

    public function test_un_jeton_de_reset_deja_utilise_ne_fonctionne_plus(): void
    {
        $utilisateur = $this->creerUtilisateur(['email' => 'verifie@example.com', 'email_verified_at' => now()]);
        $tokenBrut = 'jeton-reset-deja-pris';
        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'reinitialisation_mot_de_passe',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addMinutes(60),
            'utilise_le' => now(),
        ]);

        $response = $this->post("/reinitialiser-mot-de-passe/{$tokenBrut}", [
            'mot_de_passe' => 'nouveaumotdepasse',
            'mot_de_passe_confirmation' => 'nouveaumotdepasse',
        ]);

        $response->assertStatus(200)->assertSee('invalide');
        $this->assertTrue(Hash::check('ancienmotdepasse', $utilisateur->fresh()->password));
    }

    // --- Codes de secours (parcours 4) ---

    public function test_generer_des_codes_de_secours_en_retourne_huit(): void
    {
        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $response = $this->postJson('/api/codes-secours/generer');

        $response->assertStatus(200);
        $this->assertCount(8, $response->json('data.codes'));
        $this->assertSame(8, RecoveryCode::where('id_user_app', $utilisateur->id_user_app)->count());
    }

    public function test_regenerer_des_codes_invalide_les_anciens(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $service = new RecuperationCompteService();
        $anciensCodes = $service->genererCodesSecours($utilisateur);
        $service->genererCodesSecours($utilisateur);

        $reussi = $service->reinitialiserAvecCodeSecours(
            $utilisateur->phone,
            $anciensCodes[0],
            'peuimporte12345',
            request(),
        );

        $this->assertFalse($reussi);
    }

    public function test_le_statut_des_codes_de_secours_indique_le_nombre_restant(): void
    {
        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);
        $this->postJson('/api/codes-secours/generer');

        $response = $this->getJson('/api/codes-secours/statut');

        $response->assertStatus(200)->assertJsonPath('data.nombreCodesRestants', 8);
    }

    public function test_reinitialiser_avec_un_bon_code_de_secours_fonctionne_et_le_consomme(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $codes = (new RecuperationCompteService())->genererCodesSecours($utilisateur);

        $response = $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => $utilisateur->phone,
            'code' => $codes[0],
            'nouveau' => 'nouveaumotdepasse',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('nouveaumotdepasse', $utilisateur->fresh()->password));
        $this->assertSame(7, (new RecuperationCompteService())->nombreCodesSecoursRestants($utilisateur->fresh()));
    }

    public function test_reinitialiser_avec_un_code_de_secours_invalide_ne_change_rien(): void
    {
        $utilisateur = $this->creerUtilisateur();
        (new RecuperationCompteService())->genererCodesSecours($utilisateur);

        $response = $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => $utilisateur->phone,
            'code' => 'FAUX-CODE',
            'nouveau' => 'nouveaumotdepasse',
        ]);

        $response->assertStatus(401);
        $this->assertTrue(Hash::check('ancienmotdepasse', $utilisateur->fresh()->password));
    }

    public function test_un_code_de_secours_deja_utilise_ne_fonctionne_plus(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $codes = (new RecuperationCompteService())->genererCodesSecours($utilisateur);
        $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => $utilisateur->phone, 'code' => $codes[0], 'nouveau' => 'premierchangement',
        ]);

        $response = $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => $utilisateur->phone, 'code' => $codes[0], 'nouveau' => 'deuxiemechangement',
        ]);

        $response->assertStatus(401);
        $this->assertTrue(Hash::check('premierchangement', $utilisateur->fresh()->password));
    }

    public function test_un_numero_inconnu_avec_un_code_quelconque_ne_reinitialise_rien(): void
    {
        $response = $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => '0799999999', 'code' => 'ABCD-EFGH', 'nouveau' => 'nouveaumotdepasse',
        ]);

        $response->assertStatus(401);
    }

    public function test_l_utilisation_d_un_code_de_secours_laisse_une_trace(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $codes = (new RecuperationCompteService())->genererCodesSecours($utilisateur);

        $this->postJson('/api/mot-de-passe/code-secours', [
            'phone' => $utilisateur->phone, 'code' => $codes[0], 'nouveau' => 'nouveaumotdepasse',
        ]);

        $codeUtilise = RecoveryCode::where('id_user_app', $utilisateur->id_user_app)
            ->whereNotNull('utilise_le')
            ->first();
        $this->assertNotNull($codeUtilise);
        $this->assertNotNull($codeUtilise->utilise_ip);
    }
}
