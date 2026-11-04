<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vérifie que les bons événements déclenchent bien une notification push
 * (et une seule), demandées le 03/11/2026 : nouvelle réservation,
 * confirmation, annulation. Le service d'envoi réel (NotificationService)
 * est testé séparément (NotificationServiceTest) ; ici on vérifie
 * uniquement le branchement dans les contrôleurs.
 */
class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuseEtClient(): array
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Awa', 'phone' => '0700000090', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Fatou', 'phone' => '0700000091', 'password' => 'x', 'role' => 'user',
        ]);

        return [$coiffeuse, $client];
    }

    private function creerService(int $idCoiffeuse): int
    {
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $idCoiffeuse, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_creer_une_reservation_notifie_la_coiffeuse(): void
    {
        [$coiffeuse, $client] = $this->creerCoiffeuseEtClient();
        $idService = $this->creerService($coiffeuse->id_user_app);

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->once()
            ->withArgs(fn ($destinataire) => $destinataire->id_user_app === $coiffeuse->id_user_app);

        Sanctum::actingAs($client);
        $this->postJson('/api/reservations', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $idService,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'methode_paiement' => 'stripe',
        ])->assertStatus(201);
    }

    public function test_confirmer_une_reservation_notifie_la_cliente(): void
    {
        [$coiffeuse, $client] = $this->creerCoiffeuseEtClient();
        $idService = $this->creerService($coiffeuse->id_user_app);
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $idService,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
        ]);

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->once()
            ->withArgs(fn ($destinataire) => $destinataire->id_user_app === $client->id_user_app);

        Sanctum::actingAs($coiffeuse);
        $this->putJson("/api/reservations/confirmer/{$reservation->id_reservation}")
            ->assertStatus(200);
    }

    public function test_la_cliente_qui_annule_notifie_la_coiffeuse(): void
    {
        [$coiffeuse, $client] = $this->creerCoiffeuseEtClient();
        $idService = $this->creerService($coiffeuse->id_user_app);
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $idService,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
        ]);

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->once()
            ->withArgs(fn ($destinataire) => $destinataire->id_user_app === $coiffeuse->id_user_app);

        Sanctum::actingAs($client);
        $this->putJson("/api/reservations/{$reservation->id_reservation}", ['statut' => 'annulee'])
            ->assertStatus(200);
    }

    public function test_la_coiffeuse_qui_refuse_notifie_la_cliente(): void
    {
        [$coiffeuse, $client] = $this->creerCoiffeuseEtClient();
        $idService = $this->creerService($coiffeuse->id_user_app);
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $idService,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
        ]);

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->once()
            ->withArgs(fn ($destinataire) => $destinataire->id_user_app === $client->id_user_app);

        Sanctum::actingAs($coiffeuse);
        $this->putJson("/api/reservations/refuser/{$reservation->id_reservation}")
            ->assertStatus(200);
    }
}
