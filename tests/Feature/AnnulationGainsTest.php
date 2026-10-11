<?php

namespace Tests\Feature;

use App\Models\Gains;
use App\Models\Paiements;
use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La commission Afrolia reste due en cas d'annulation d'une réservation
 * déjà payée : annulerGainsPourReservation() (ApiReservationsController)
 * ajuste le gain déjà créé au paiement plutôt que de le laisser tel quel.
 */
class AnnulationGainsTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);

        $faux = new class implements StripeGatewayInterface {
            public function createPaymentIntent(float $montant): array
            {
                return ['id' => 'pi_annulation_test', 'client_secret' => 'secret_x', 'status' => 'requires_payment_method'];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'client_secret' => 'secret_x', 'status' => 'succeeded'];
            }

            public function refund(string $paymentIntentId, float $montant): array
            {
                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);
    }

    private function signerPayload(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Crée une réservation, la paie réellement via le flux Stripe (webhook
     * inclus) pour obtenir un vrai gain "en_attente" comme en production,
     * et retourne [client, coiffeuse, id_reservation].
     */
    private function creerReservationPayee(): array
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000040', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000041', 'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($client);

        $creation = $this->postJson('/api/reservations', [
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'methode_paiement' => 'stripe',
        ]);
        $idReservation = $creation->json('data.id_reservation');

        $this->postJson('/api/paiements', [
            'montant' => 10000,
            'id_reservation' => $idReservation,
            'methode' => 'stripe',
        ])->assertStatus(200);

        $paiement = Paiements::where('id_reservation', $idReservation)->latest('id_paiement')->first();

        $payload = json_encode([
            'id' => 'evt_annulation_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);
        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload)->assertStatus(200);

        $this->assertDatabaseHas('gains', ['id_reservation' => $idReservation]);

        return [$client, $coiffeuse, $idReservation];
    }

    public function test_annulation_par_la_cliente_annule_le_gain_sans_penaliser_la_coiffeuse(): void
    {
        [$client, $coiffeuse, $idReservation] = $this->creerReservationPayee();

        Sanctum::actingAs($client);
        $this->putJson("/api/reservations/{$idReservation}", [
            'statut' => 'annulee',
            'raison_annulation' => 'Empêchement',
            // Tentative de falsification : le client essaie de faire croire
            // que c'est la coiffeuse qui annule, pour échapper à la
            // commission. Le serveur doit l'ignorer.
            'annule_par' => 'coiffeur',
        ])->assertStatus(200);

        $reservation = Reservations::find($idReservation);
        $this->assertSame('client', $reservation->annule_par);

        $gain = Gains::where('id_reservation', $idReservation)->first();
        $this->assertSame(0.0, (float) $gain->montant_net);
        $this->assertSame('annulee', $gain->statut);
    }

    public function test_annulation_par_la_coiffeuse_penalise_ses_gains_du_montant_de_la_commission(): void
    {
        [$client, $coiffeuse, $idReservation] = $this->creerReservationPayee();

        Sanctum::actingAs($coiffeuse);
        $this->putJson("/api/reservations/refuser/{$idReservation}", [
            'raison' => 'Indisponible',
        ])->assertStatus(200);

        $gain = Gains::where('id_reservation', $idReservation)->first();
        $this->assertSame('en_attente', $gain->statut);
        $this->assertSame(-800.0, (float) $gain->montant_net);
    }
}
