<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenApiTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => Hash::make('motdepasse'),
            'role' => 'user',
        ]);
    }

    public function test_une_route_protegee_sans_jeton_est_refusee(): void
    {
        $this->postJson('/api/device-tokens', ['token' => 'abc', 'plateforme' => 'android'])
            ->assertStatus(401);
    }

    public function test_enregistre_un_nouveau_jeton(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000001');
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/device-tokens', ['token' => 'jeton-abc', 'plateforme' => 'android'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'jeton-abc',
            'id_user_app' => $utilisateur->id_user_app,
            'plateforme' => 'android',
        ]);
    }

    public function test_refuse_une_plateforme_inconnue(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000001');
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/device-tokens', ['token' => 'jeton-abc', 'plateforme' => 'windows'])
            ->assertStatus(422);
    }

    public function test_reassigne_un_jeton_existant_a_un_nouvel_utilisateur_sans_le_dupliquer(): void
    {
        $ancienUtilisateur = $this->creerUtilisateur('0700000001');
        $nouvelUtilisateur = $this->creerUtilisateur('0700000002');

        DeviceToken::create([
            'id_user_app' => $ancienUtilisateur->id_user_app,
            'token' => 'jeton-partage',
            'plateforme' => 'android',
        ]);

        Sanctum::actingAs($nouvelUtilisateur);

        $this->postJson('/api/device-tokens', ['token' => 'jeton-partage', 'plateforme' => 'android'])
            ->assertStatus(200);

        // Une seule ligne pour ce jeton, réassignée au nouvel utilisateur.
        $this->assertSame(1, DeviceToken::where('token', 'jeton-partage')->count());
        $this->assertSame(
            $nouvelUtilisateur->id_user_app,
            DeviceToken::where('token', 'jeton-partage')->first()->id_user_app,
        );
    }

    public function test_supprime_son_propre_jeton(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000001');
        DeviceToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'token' => 'jeton-a-retirer',
            'plateforme' => 'ios',
        ]);

        Sanctum::actingAs($utilisateur);

        $this->deleteJson('/api/device-tokens', ['token' => 'jeton-a-retirer'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'jeton-a-retirer']);
    }

    public function test_ne_peut_pas_supprimer_le_jeton_d_un_autre_utilisateur(): void
    {
        $proprietaire = $this->creerUtilisateur('0700000001');
        $autre = $this->creerUtilisateur('0700000002');

        DeviceToken::create([
            'id_user_app' => $proprietaire->id_user_app,
            'token' => 'jeton-protege',
            'plateforme' => 'ios',
        ]);

        Sanctum::actingAs($autre);

        $this->deleteJson('/api/device-tokens', ['token' => 'jeton-protege'])
            ->assertStatus(200);

        // Toujours là : supprimer ne touche que les jetons de l'appelant.
        $this->assertDatabaseHas('device_tokens', ['token' => 'jeton-protege']);
    }
}
