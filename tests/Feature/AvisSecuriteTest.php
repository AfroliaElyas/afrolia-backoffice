<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvisSecuriteTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone, string $role): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test', 'last_name' => 'Test', 'phone' => $phone, 'password' => 'x', 'role' => $role,
        ]);
    }

    private function creerReservation(UsersApp $client, UsersApp $coiffeuse, string $statut): Reservations
    {
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'commission' => 500,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Reservations::create([
            'numero_reservation' => 'RES-TEST-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => $statut,
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => 'cash',
        ]);
    }

    public function test_un_client_ne_peut_pas_noter_avec_la_reservation_de_quelqu_un_d_autre(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000050', 'hair');
        $proprietaire = $this->creerUtilisateur('0700000051', 'user');
        $intrus = $this->creerUtilisateur('0700000052', 'user');
        $reservation = $this->creerReservation($proprietaire, $coiffeuse, 'terminee');

        Sanctum::actingAs($intrus);

        $this->postJson('/api/avis', [
            'rating' => 1,
            'id_stylist' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
        ])->assertStatus(403);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_un_avis_ne_peut_pas_citer_une_coiffeuse_qui_n_a_pas_fait_la_prestation(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000053', 'hair');
        $autreCoiffeuse = $this->creerUtilisateur('0700000054', 'hair');
        $client = $this->creerUtilisateur('0700000055', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse, 'terminee');

        Sanctum::actingAs($client);

        $this->postJson('/api/avis', [
            'rating' => 1,
            'id_stylist' => $autreCoiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
        ])->assertStatus(422);
    }

    public function test_impossible_de_noter_une_reservation_pas_encore_terminee(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000056', 'hair');
        $client = $this->creerUtilisateur('0700000057', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse, 'confirmee');

        Sanctum::actingAs($client);

        $this->postJson('/api/avis', [
            'rating' => 5,
            'id_stylist' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
        ])->assertStatus(422);
    }

    public function test_impossible_de_laisser_deux_avis_sur_la_meme_reservation(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000058', 'hair');
        $client = $this->creerUtilisateur('0700000059', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse, 'terminee');

        Sanctum::actingAs($client);

        $body = [
            'rating' => 4,
            'id_stylist' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
        ];

        $this->postJson('/api/avis', $body)->assertStatus(201);
        $this->postJson('/api/avis', $body)->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }
}
