<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Messagerie entre une cliente et sa coiffeuse autour d'une réservation
 * précise : échanger le lieu, un détail de dernière minute, etc.
 */
class MessagesReservationTest extends TestCase
{
    use RefreshDatabase;

    private function creerReservation(): array
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000080', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000081', 'password' => 'x', 'role' => 'user',
        ]);
        $autre = UsersApp::create([
            'name' => 'Autre', 'last_name' => 'Test', 'phone' => '0700000082', 'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'confirmee',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
        ]);

        return [$client, $coiffeuse, $autre, $reservation];
    }

    public function test_la_cliente_peut_envoyer_un_message_a_sa_coiffeuse(): void
    {
        [$client, $coiffeuse, , $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", [
            'message' => "Je suis au rez-de-chaussée, immeuble bleu.",
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('messages', [
            'id_reservation' => $reservation->id_reservation,
            'id_expediteur' => $client->id_user_app,
            'message' => "Je suis au rez-de-chaussée, immeuble bleu.",
            'lu' => false,
        ]);
    }

    public function test_la_coiffeuse_voit_et_peut_repondre_au_message(): void
    {
        [$client, $coiffeuse, , $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Bonjour !'])
            ->assertStatus(201);

        Sanctum::actingAs($coiffeuse);
        $liste = $this->getJson("/api/reservations/{$reservation->id_reservation}/messages");
        $liste->assertStatus(200);
        $this->assertCount(1, $liste->json('data'));

        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Bonjour, à tout à l\'heure !'])
            ->assertStatus(201);

        $liste2 = $this->getJson("/api/reservations/{$reservation->id_reservation}/messages");
        $this->assertCount(2, $liste2->json('data'));
    }

    public function test_consulter_les_messages_marque_comme_lus_ceux_recus(): void
    {
        [$client, $coiffeuse, , $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Bonjour !']);

        $this->assertDatabaseHas('messages', ['id_reservation' => $reservation->id_reservation, 'lu' => false]);

        Sanctum::actingAs($coiffeuse);
        $this->getJson("/api/reservations/{$reservation->id_reservation}/messages")->assertStatus(200);

        $this->assertDatabaseHas('messages', ['id_reservation' => $reservation->id_reservation, 'lu' => true]);
    }

    public function test_une_personne_etrangere_a_la_reservation_ne_peut_ni_lire_ni_ecrire(): void
    {
        [, , $autre, $reservation] = $this->creerReservation();
        Sanctum::actingAs($autre);

        $this->getJson("/api/reservations/{$reservation->id_reservation}/messages")->assertStatus(404);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Salut'])
            ->assertStatus(404);
        $this->assertSame(0, Message::count());
    }

    public function test_refuse_un_message_vide(): void
    {
        [$client, , , $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);

        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => ''])
            ->assertStatus(422);
        $this->assertSame(0, Message::count());
    }

    public function test_compte_les_messages_non_lus_tous_azimuts(): void
    {
        [$client, $coiffeuse, , $reservation] = $this->creerReservation();

        Sanctum::actingAs($client);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Un']);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Deux']);

        Sanctum::actingAs($coiffeuse);
        $reponse = $this->getJson('/api/messages/non-lus');
        $reponse->assertStatus(200)->assertJsonPath('data.non_lus', 2);

        // Consulter la conversation marque les messages comme lus : le
        // compteur retombe à 0.
        $this->getJson("/api/reservations/{$reservation->id_reservation}/messages");
        $this->getJson('/api/messages/non-lus')->assertJsonPath('data.non_lus', 0);
    }

    public function test_les_messages_dune_autre_reservation_ne_comptent_pas(): void
    {
        [$client, $coiffeuse, , $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);
        $this->postJson("/api/reservations/{$reservation->id_reservation}/messages", ['message' => 'Un']);

        $autreCoiffeuse = UsersApp::create([
            'name' => 'Autre coiffeuse', 'last_name' => 'Test', 'phone' => '0700000083', 'password' => 'x', 'role' => 'hair',
        ]);
        Sanctum::actingAs($autreCoiffeuse);
        $this->getJson('/api/messages/non-lus')->assertJsonPath('data.non_lus', 0);
    }
}
