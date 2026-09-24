<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthentificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone, string $role): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => Hash::make('motdepasse'),
            'role' => $role,
        ]);
    }

    public function test_une_route_protegee_sans_jeton_est_refusee(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000001', 'user');

        $this->postJson("/api/update/{$utilisateur->id_user_app}", ['nom' => 'Autre'])
            ->assertStatus(401);
    }

    public function test_un_jeton_invalide_est_refuse(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000001', 'user');

        $this->withHeader('Authorization', 'Bearer un-jeton-qui-n-existe-pas')
            ->postJson("/api/update/{$utilisateur->id_user_app}", ['nom' => 'Autre'])
            ->assertStatus(401);
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_compte_d_un_autre(): void
    {
        $utilisateurA = $this->creerUtilisateur('0700000001', 'user');
        $utilisateurB = $this->creerUtilisateur('0700000002', 'user');

        Sanctum::actingAs($utilisateurA);

        $this->postJson("/api/update/{$utilisateurB->id_user_app}", ['nom' => 'Usurpation'])
            ->assertStatus(403);

        $this->assertSame('Test', $utilisateurB->fresh()->name);
    }

    public function test_un_utilisateur_ne_peut_pas_supprimer_le_compte_d_un_autre(): void
    {
        $utilisateurA = $this->creerUtilisateur('0700000001', 'user');
        $utilisateurB = $this->creerUtilisateur('0700000002', 'user');

        Sanctum::actingAs($utilisateurA);

        $this->deleteJson("/api/delete/{$utilisateurB->id_user_app}")
            ->assertStatus(403);

        $this->assertNotNull($utilisateurB->fresh());
    }

    public function test_une_coiffeuse_ne_peut_pas_consulter_l_abonnement_d_une_autre(): void
    {
        $coiffeuseA = $this->creerUtilisateur('0700000003', 'hair');
        $coiffeuseB = $this->creerUtilisateur('0700000004', 'hair');

        Sanctum::actingAs($coiffeuseA);

        $this->getJson("/api/abonnement/{$coiffeuseB->id_user_app}")
            ->assertStatus(403);
    }

    public function test_le_flux_complet_connexion_puis_requete_authentifiee_fonctionne(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000005', 'user');

        $connexion = $this->postJson('/api/login', [
            'login' => '0700000005',
            'password' => 'motdepasse',
        ]);

        $connexion->assertStatus(200);
        $token = $connexion->json('data.token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('id_user_app', $utilisateur->id_user_app);

        $this->assertSame(1, PersonalAccessToken::count());

        // La déconnexion révoque bien le jeton côté serveur (un appareil qui
        // referait la requête avec ce même jeton recevrait désormais un 401 :
        // non vérifiable ici via une deuxième requête de test, le guard
        // d'authentification restant mémorisé pour la durée du test).
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        $this->assertSame(0, PersonalAccessToken::count());
    }
}
