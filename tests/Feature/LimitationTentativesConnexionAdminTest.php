<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Régression pour la faille relevée par Emergent AI (25/10/2026) :
 * /custom-login n'imposait aucune limite de tentatives, permettant un
 * essai systématique de mots de passe sur le compte admin.
 */
class LimitationTentativesConnexionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin@afrolia-app.com|127.0.0.1');
    }

    public function test_bloque_apres_cinq_mauvais_mots_de_passe(): void
    {
        $admin = User::factory()->create(['email' => 'admin@afrolia-app.com']);

        for ($i = 0; $i < 5; $i++) {
            $reponse = $this->post('/custom-login', [
                'email' => $admin->email,
                'password' => 'mauvais-mot-de-passe',
            ]);
            $reponse->assertSessionHasErrors();
            $this->assertGuest();
        }

        // 6e tentative, cette fois avec le BON mot de passe : doit quand
        // même être bloquée, preuve que c'est bien le nombre d'essais qui
        // compte, pas seulement un mauvais mot de passe répété.
        $reponse = $this->post('/custom-login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $reponse->assertSessionHasErrors();
        $this->assertGuest();
        $this->assertStringContainsString(
            'Trop de tentatives',
            collect(session('errors')->getBag('default')->all())->implode(' ')
        );
    }

    public function test_une_connexion_reussie_ne_declenche_jamais_le_blocage(): void
    {
        $admin = User::factory()->create(['email' => 'admin2@afrolia-app.com']);

        // Deux échecs, bien en-dessous de la limite, puis un succès.
        $this->post('/custom-login', ['email' => $admin->email, 'password' => 'faux']);
        $this->post('/custom-login', ['email' => $admin->email, 'password' => 'faux']);

        $reponse = $this->post('/custom-login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $reponse->assertRedirect('index');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_le_blocage_est_propre_a_chaque_compte(): void
    {
        $admin = User::factory()->create(['email' => 'admin3@afrolia-app.com']);
        $autreAdmin = User::factory()->create(['email' => 'admin4@afrolia-app.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/custom-login', [
                'email' => $admin->email,
                'password' => 'mauvais-mot-de-passe',
            ]);
        }

        // Le compte visé est bloqué...
        $bloque = $this->post('/custom-login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->assertGuest();

        // ...mais un autre compte, depuis la même IP de test, reste utilisable.
        $libre = $this->post('/custom-login', [
            'email' => $autreAdmin->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($autreAdmin);
    }
}
