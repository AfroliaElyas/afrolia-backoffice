<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationsSecuriteTest extends TestCase
{
    use RefreshDatabase;

    private function creerReservation(UsersApp $client, UsersApp $coiffeuse): Reservations
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
            'numero_reservation' => 'RES-TEST-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'en_attente',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => 'cash',
        ]);
    }

    public function test_un_client_peut_reprogrammer_ou_annuler_sa_reservation(): void
    {
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000030', 'password' => 'x', 'role' => 'user']);
        $coiffeuse = UsersApp::create(['name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000031', 'password' => 'x', 'role' => 'hair']);
        $reservation = $this->creerReservation($client, $coiffeuse);
        Sanctum::actingAs($client);

        $this->putJson("/api/reservations/{$reservation->id_reservation}", [
            'statut' => 'annulee',
            'raison_annulation' => 'Empêchement',
            'annule_par' => 'client',
        ])->assertStatus(200);

        $this->assertSame('annulee', $reservation->fresh()->statut);
    }

    public function test_un_client_ne_peut_plus_falsifier_le_paiement_ou_le_statut_de_sa_reservation(): void
    {
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000032', 'password' => 'x', 'role' => 'user']);
        $coiffeuse = UsersApp::create(['name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000033', 'password' => 'x', 'role' => 'hair']);
        $reservation = $this->creerReservation($client, $coiffeuse);
        Sanctum::actingAs($client);

        // "confirmee"/"terminee" n'appartiennent qu'aux actions dédiées de
        // la coiffeuse : la valeur est rejetée par la validation.
        $this->putJson("/api/reservations/{$reservation->id_reservation}", [
            'statut' => 'terminee',
        ])->assertStatus(422);

        // Les champs financiers et le statut de paiement ne sont plus
        // acceptés du tout par cet endpoint : ils sont silencieusement
        // ignorés plutôt que de faire échouer la requête, pour ne pas
        // bloquer une reprogrammation légitime envoyée avec ces champs en
        // trop par un client plus ancien.
        $this->putJson("/api/reservations/{$reservation->id_reservation}", [
            'heure_reservation' => '15:00',
            'statut_paiement' => 'paye',
            'montant_total' => 0,
            'prix_service' => 0,
            'montant_commission' => 0,
        ])->assertStatus(200);

        $reservation->refresh();
        $this->assertSame('en_attente', $reservation->statut_paiement);
        $this->assertEquals(10500, $reservation->montant_total);
        $this->assertEquals(10000, $reservation->prix_service);
        $this->assertSame('15:00', $reservation->heure_reservation);
    }
}
