<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectionsRestantesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inscription_refuse_un_mot_de_passe_de_moins_de_8_caracteres(): void
    {
        $reponse = $this->postJson('/api/register', [
            'nom' => 'Kouassi',
            'prenom' => 'Awa',
            'phone' => '+2250700000001',
            'password' => '1234',
            'role' => 'user',
        ]);

        $reponse->assertStatus(422);
        $this->assertDatabaseMissing('users_app', ['phone' => '+2250700000001']);
    }

    public function test_la_suppression_de_compte_par_simple_lien_get_est_refusee(): void
    {
        $utilisateur = UsersApp::create([
            'name' => 'Awa',
            'last_name' => 'Kouassi',
            'phone' => '+2250700000002',
            'password' => Hash::make('motdepasse'),
            'role' => 'user',
            'statut' => 'Active',
        ]);
        Sanctum::actingAs($utilisateur);

        $this->getJson("/api/delete/{$utilisateur->id_user_app}")->assertStatus(405);
        $this->assertDatabaseHas('users_app', ['id_user_app' => $utilisateur->id_user_app]);
    }

    public function test_une_admin_non_connectee_est_renvoyee_vers_la_page_de_connexion_admin(): void
    {
        $this->get('/index')->assertRedirect('/admin');
    }
}
