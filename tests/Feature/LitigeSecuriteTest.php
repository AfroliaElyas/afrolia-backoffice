<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LitigeSecuriteTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone, string $role): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test', 'last_name' => 'Test', 'phone' => $phone, 'password' => 'x', 'role' => $role,
        ]);
    }

    private function creerReservation(UsersApp $client, UsersApp $coiffeuse, string $statut = 'terminee'): Reservations
    {
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
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

    public function test_la_cliente_peut_signaler_un_litige_sur_sa_propre_reservation(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000070', 'hair');
        $client = $this->creerUtilisateur('0700000071', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse);

        Sanctum::actingAs($client);

        $this->postJson('/api/litiges', [
            'id_reservation' => $reservation->id_reservation,
            'type' => 'qualite_service',
            'description' => 'La prestation ne correspondait pas à ce qui était affiché.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('litiges', [
            'id_reservation' => $reservation->id_reservation,
            'id_plaignant' => $client->id_user_app,
            'type' => 'qualite_service',
            'statut' => 'ouvert',
        ]);
    }

    public function test_la_coiffeuse_peut_aussi_signaler_un_litige_sur_la_reservation(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000072', 'hair');
        $client = $this->creerUtilisateur('0700000073', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse);

        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/litiges', [
            'id_reservation' => $reservation->id_reservation,
            'type' => 'comportement',
            'description' => 'La cliente a été agressive verbalement.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('litiges', [
            'id_reservation' => $reservation->id_reservation,
            'id_plaignant' => $coiffeuse->id_user_app,
        ]);
    }

    public function test_un_tiers_ne_peut_pas_signaler_un_litige_sur_la_reservation_de_quelqu_un_d_autre(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000074', 'hair');
        $client = $this->creerUtilisateur('0700000075', 'user');
        $intrus = $this->creerUtilisateur('0700000076', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse);

        Sanctum::actingAs($intrus);

        $this->postJson('/api/litiges', [
            'id_reservation' => $reservation->id_reservation,
            'type' => 'autre',
            'description' => 'Je signale pour une réservation qui ne me concerne pas.',
        ])->assertStatus(403);

        $this->assertDatabaseCount('litiges', 0);
    }

    public function test_un_type_de_litige_invalide_est_rejete(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000077', 'hair');
        $client = $this->creerUtilisateur('0700000078', 'user');
        $reservation = $this->creerReservation($client, $coiffeuse);

        Sanctum::actingAs($client);

        $this->postJson('/api/litiges', [
            'id_reservation' => $reservation->id_reservation,
            'type' => 'type_inexistant',
            'description' => 'Peu importe.',
        ])->assertStatus(422);
    }

    public function test_mes_litiges_ne_retourne_que_les_signalements_de_l_utilisateur_authentifie(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000079', 'hair');
        $client = $this->creerUtilisateur('0700000080', 'user');
        $autreClient = $this->creerUtilisateur('0700000081', 'user');
        $reservationClient = $this->creerReservation($client, $coiffeuse);
        $reservationAutre = $this->creerReservation($autreClient, $coiffeuse);

        Sanctum::actingAs($client);
        $this->postJson('/api/litiges', [
            'id_reservation' => $reservationClient->id_reservation,
            'type' => 'autre',
            'description' => 'Mon signalement à moi.',
        ])->assertStatus(201);

        Sanctum::actingAs($autreClient);
        $this->postJson('/api/litiges', [
            'id_reservation' => $reservationAutre->id_reservation,
            'type' => 'autre',
            'description' => 'Le signalement de quelqu\'un d\'autre.',
        ])->assertStatus(201);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/litiges')->assertStatus(200);

        $response->assertJsonCount(1, 'data');
        $this->assertSame(
            'Mon signalement à moi.',
            $response->json('data.0.description')
        );
    }
}
