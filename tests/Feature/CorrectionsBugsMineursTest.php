<?php

namespace Tests\Feature;

use App\Models\ClientFavorite;
use App\Models\Reservations;
use App\Models\Reviews;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectionsBugsMineursTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone, string $role): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => 'x',
            'role' => $role,
        ]);
    }

    private function creerReservationTerminee(UsersApp $client, UsersApp $coiffeuse): Reservations
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
            'statut' => 'terminee',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => 'cash',
        ]);
    }

    public function test_la_modification_du_nom_et_de_la_photo_du_compte_fonctionne(): void
    {
        $utilisateur = $this->creerUtilisateur('0700000010', 'user');
        Sanctum::actingAs($utilisateur);

        $response = $this->postJson("/api/update/{$utilisateur->id_user_app}", [
            'nom' => 'Nouveau Nom',
            'prenom' => 'Nouveau Prenom',
        ]);

        $response->assertStatus(200);
        $utilisateur->refresh();
        $this->assertSame('Nouveau Nom', $utilisateur->name);
        $this->assertSame('Nouveau Prenom', $utilisateur->last_name);
    }

    public function test_creer_et_lister_un_avis_utilise_les_bonnes_colonnes(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000011', 'hair');
        $client = $this->creerUtilisateur('0700000012', 'user');
        $reservation = $this->creerReservationTerminee($client, $coiffeuse);
        Sanctum::actingAs($client);

        $creation = $this->postJson('/api/avis', [
            'rating' => 4,
            'comment' => 'Très bon service',
            'id_stylist' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
        ]);

        $creation->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'id_client' => $client->id_user_app,
            'id_stylist' => $coiffeuse->id_user_app,
            'rating' => 4,
        ]);

        $liste = $this->getJson("/api/avis/coiffeuse/{$coiffeuse->id_user_app}");
        $liste->assertStatus(200);
        $liste->assertJsonPath('data.0.rating', 4);
        $liste->assertJsonPath('data.0.client.name', 'Test');
    }

    public function test_un_client_ne_peut_pas_modifier_l_avis_d_un_autre(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000013', 'hair');
        $clientA = $this->creerUtilisateur('0700000014', 'user');
        $clientB = $this->creerUtilisateur('0700000015', 'user');
        $reservation = $this->creerReservationTerminee($clientA, $coiffeuse);

        $avis = Reviews::create([
            'id_client' => $clientA->id_user_app,
            'id_stylist' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
            'rating' => 5,
            'comment' => 'Parfait',
        ]);

        Sanctum::actingAs($clientB);

        $this->putJson("/api/avis/{$avis->id_review}", ['rating' => 1])
            ->assertStatus(403);

        $this->assertSame(5, $avis->fresh()->rating);
    }

    public function test_la_liste_des_favoris_utilise_les_bonnes_colonnes(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000016', 'hair');
        $client = $this->creerUtilisateur('0700000017', 'user');

        ClientFavorite::create([
            'client_id' => $client->id_user_app,
            'stylist_id' => $coiffeuse->id_user_app,
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson("/api/favoris/client/{$client->id_user_app}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.name', 'Test');
        $response->assertJsonPath('data.0.last_name', 'Test');
    }

    public function test_la_liste_des_reservations_d_un_client_fonctionne(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000018', 'hair');
        $client = $this->creerUtilisateur('0700000019', 'user');
        $reservation = $this->creerReservationTerminee($client, $coiffeuse);

        Sanctum::actingAs($client);

        $response = $this->getJson("/api/reservations/user/{$client->id_user_app}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id_reservation', $reservation->id_reservation);
        $response->assertJsonPath('data.0.nom_coiffeuse', 'Test');
    }

    public function test_un_client_ne_peut_pas_consulter_les_reservations_d_un_autre(): void
    {
        $clientA = $this->creerUtilisateur('0700000020', 'user');
        $clientB = $this->creerUtilisateur('0700000021', 'user');

        Sanctum::actingAs($clientB);

        $this->getJson("/api/reservations/user/{$clientA->id_user_app}")
            ->assertStatus(403);
    }
}
