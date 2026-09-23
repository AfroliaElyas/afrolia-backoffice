<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(string $formule): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse',
            'last_name' => 'Test',
            'phone' => '0700000001',
            'password' => 'x',
            'role' => 'hair',
            'formule_abonnement' => $formule,
        ]);
    }

    private function creerClient(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Client',
            'last_name' => 'Test',
            'phone' => '0700000002',
            'password' => 'x',
            'role' => 'user',
        ]);
    }

    private function creerService(int $idCoiffeur, float $prix): int
    {
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('services')->insertGetId([
            'prix' => $prix,
            'minute' => 60,
            'commission' => $prix * 0.15, // valeur figée à la création du service, volontairement ignorée par la réservation
            'id_utilisateur' => $idCoiffeur,
            'id_speciale' => $specialite,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function corpsReservation(int $idClient, int $idCoiffeur, int $idService, float $prix): array
    {
        return [
            'id_client' => $idClient,
            'id_coiffeur' => $idCoiffeur,
            'id_service' => $idService,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => $prix,
            // Valeurs volontairement fausses (calculées à 15 %, comme le
            // faisait l'app avant correction) : le serveur doit les ignorer.
            'montant_commission' => round($prix * 0.15, 2),
            'montant_total' => round($prix * 1.15, 2),
            'methode_paiement' => 'stripe',
        ];
    }

    public function test_la_commission_reservation_reflete_la_formule_gratuite(): void
    {
        $coiffeuse = $this->creerCoiffeuse('gratuit');
        $client = $this->creerClient();
        $service = $this->creerService($coiffeuse->id_user_app, 10000);

        $response = $this->postJson('/api/reservations', $this->corpsReservation(
            $client->id_user_app,
            $coiffeuse->id_user_app,
            $service,
            10000
        ));

        $response->assertStatus(201);
        $response->assertJsonPath('data.montant_commission', 500);
        $response->assertJsonPath('data.montant_total', 10500);
    }

    public function test_la_commission_reservation_reflete_la_formule_standard(): void
    {
        $coiffeuse = $this->creerCoiffeuse('standard');
        $client = $this->creerClient();
        $service = $this->creerService($coiffeuse->id_user_app, 10000);

        $response = $this->postJson('/api/reservations', $this->corpsReservation(
            $client->id_user_app,
            $coiffeuse->id_user_app,
            $service,
            10000
        ));

        $response->assertStatus(201);
        $response->assertJsonPath('data.montant_commission', 300);
        $response->assertJsonPath('data.montant_total', 10300);
    }

    public function test_la_commission_reservation_reflete_la_formule_premium(): void
    {
        $coiffeuse = $this->creerCoiffeuse('premium');
        $client = $this->creerClient();
        $service = $this->creerService($coiffeuse->id_user_app, 10000);

        $response = $this->postJson('/api/reservations', $this->corpsReservation(
            $client->id_user_app,
            $coiffeuse->id_user_app,
            $service,
            10000
        ));

        $response->assertStatus(201);
        $response->assertJsonPath('data.montant_commission', 0);
        $response->assertJsonPath('data.montant_total', 10000);
    }

    public function test_le_paiement_utilise_le_montant_reel_de_la_reservation_pas_celui_envoye_par_le_client(): void
    {
        $coiffeuse = $this->creerCoiffeuse('premium');
        $client = $this->creerClient();
        $service = $this->creerService($coiffeuse->id_user_app, 10000);

        $creation = $this->postJson('/api/reservations', $this->corpsReservation(
            $client->id_user_app,
            $coiffeuse->id_user_app,
            $service,
            10000
        ));

        $idReservation = $creation->json('data.id_reservation');
        $this->assertSame(10000, $creation->json('data.montant_total'));

        // Le client envoie un montant totalement différent (ex. calcul local
        // périmé à 15 %, ou tentative de manipulation) : le serveur doit
        // l'ignorer et facturer le montant réel de la réservation.
        $paiement = $this->postJson('/api/paiements', [
            'montant' => 11500,
            'id_reservation' => $idReservation,
            'methode' => 'mobile_money',
            'operateur' => 'orange',
            'telephone' => '0700000009',
        ]);

        $paiement->assertStatus(200);

        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $idReservation,
            'amount' => 10000,
        ]);

        $reservation = Reservations::find($idReservation);
        $this->assertEquals(10000, (float) $reservation->montant_total);
    }
}
