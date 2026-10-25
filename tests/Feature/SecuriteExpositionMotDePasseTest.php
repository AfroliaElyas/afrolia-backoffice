<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Régression pour la faille relevée par Emergent AI (25/10/2026) :
 * GET /user renvoyait le mot de passe haché de l'utilisateur authentifié,
 * parce que UsersApp n'avait pas de $hidden contrairement au modèle User.
 */
class SecuriteExpositionMotDePasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_route_user_ne_renvoie_jamais_le_mot_de_passe(): void
    {
        $utilisateur = UsersApp::create([
            'name' => 'Aïcha',
            'last_name' => 'Test',
            'phone' => '0700000099',
            'password' => bcrypt('mot-de-passe-secret'),
            'role' => 'user',
        ]);

        Sanctum::actingAs($utilisateur);

        $response = $this->getJson('/api/user');

        $response->assertOk();
        $response->assertJsonMissing(['password' => $utilisateur->password]);
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertStringNotContainsString(
            $utilisateur->password,
            $response->getContent(),
            'Le hash du mot de passe ne doit jamais apparaître dans la réponse JSON.'
        );
    }

    public function test_la_serialisation_json_dun_users_app_masque_le_mot_de_passe(): void
    {
        $utilisateur = UsersApp::create([
            'name' => 'Fatou',
            'last_name' => 'Test',
            'phone' => '0700000098',
            'password' => bcrypt('autre-secret'),
            'role' => 'hair',
        ]);

        $tableau = $utilisateur->toArray();

        $this->assertArrayNotHasKey('password', $tableau);
    }
}
