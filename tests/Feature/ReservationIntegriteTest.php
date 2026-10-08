<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\Services;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationIntegriteTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(string $phone = '0700000080'): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => $phone, 'password' => 'x', 'role' => 'hair',
        ]);
    }

    private function creerClient(string $phone = '0700000081'): UsersApp
    {
        return UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => $phone, 'password' => 'x', 'role' => 'user',
        ]);
    }

    private function creerService(int $idCoiffeur, float $prix = 10000, int $minute = 60): Services
    {
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Services::create([
            'prix' => $prix, 'minute' => $minute, 'description' => 'Test', 'commission' => 0,
            'id_utilisateur' => $idCoiffeur, 'id_speciale' => $specialite,
        ]);
    }

    public function test_le_prix_facture_est_celui_du_service_pas_celui_envoye_par_le_client(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $client = $this->creerClient();
        $service = $this->creerService($coiffeuse->id_user_app, 10000);
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 1, // tentative de manipulation
            'methode_paiement' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(10000, $response->json('data.prix_service'));
    }

    public function test_impossible_de_reserver_un_service_qui_n_appartient_pas_a_la_coiffeuse_indiquee(): void
    {
        $coiffeuseA = $this->creerCoiffeuse('0700000082');
        $coiffeuseB = $this->creerCoiffeuse('0700000083');
        $client = $this->creerClient('0700000084');
        $serviceDeA = $this->creerService($coiffeuseA->id_user_app);
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuseB->id_user_app,
            'id_service' => $serviceDeA->id_service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_impossible_de_reserver_un_creneau_deja_passe(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000085');
        $client = $this->creerClient('0700000086');
        $service = $this->creerService($coiffeuse->id_user_app);
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => now()->subDay()->toDateString(),
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_deux_creneaux_qui_se_chevauchent_chez_la_meme_coiffeuse_sont_refuses(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000087');
        $clientA = $this->creerClient('0700000088');
        $clientB = $this->creerClient('0700000089');
        $service = $this->creerService($coiffeuse->id_user_app, 10000, 60);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($clientA);
        $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ])->assertStatus(201);

        Sanctum::actingAs($clientB);
        // 10h30 chevauche le premier rendez-vous (10h00-11h00).
        $response = $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '10:30',
            'methode_paiement' => 'cash',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_un_creneau_juste_apres_le_precedent_est_accepte(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000090');
        $clientA = $this->creerClient('0700000091');
        $clientB = $this->creerClient('0700000092');
        $service = $this->creerService($coiffeuse->id_user_app, 10000, 60);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($clientA);
        $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ])->assertStatus(201);

        Sanctum::actingAs($clientB);
        $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '11:00',
            'methode_paiement' => 'cash',
        ])->assertStatus(201);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_une_reservation_annulee_ne_bloque_plus_le_creneau(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000093');
        $client = $this->creerClient('0700000094');
        $service = $this->creerService($coiffeuse->id_user_app, 10000, 60);
        $date = now()->addDay()->toDateString();

        $annulee = Reservations::create([
            'numero_reservation' => 'RES-TEST-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '10:00',
            'duree_minutes' => 60,
            'statut' => 'annulee',
            'prix_service' => 10000,
            'montant_commission' => 0,
            'montant_total' => 10000,
            'methode_paiement' => 'cash',
        ]);

        Sanctum::actingAs($client);
        $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => $date,
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ])->assertStatus(201);
    }

    public function test_la_duree_de_la_reservation_est_figee_a_la_creation(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000095');
        $client = $this->creerClient('0700000096');
        $service = $this->creerService($coiffeuse->id_user_app, 10000, 45);
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service->id_service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'methode_paiement' => 'cash',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.duree_minutes', 45);

        // La coiffeuse allonge ensuite la durée du service : la réservation
        // déjà prise ne doit pas changer rétroactivement.
        $service->update(['minute' => 120]);
        $idReservation = $response->json('data.id_reservation');

        $this->assertSame(45, Reservations::find($idReservation)->duree_minutes);
    }
}
