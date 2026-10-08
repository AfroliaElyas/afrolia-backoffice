<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\Reservations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    private function creerReservationAvecPaiementPending(): Paiements
    {
        $coiffeur = DB::table('users_app')->insertGetId([
            'name' => 'Coiffeuse',
            'last_name' => 'Test',
            'phone' => '0700000001',
            'password' => 'x',
            'role' => 'hair',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = DB::table('users_app')->insertGetId([
            'name' => 'Client',
            'last_name' => 'Test',
            'phone' => '0700000002',
            'password' => 'x',
            'role' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = DB::table('services')->insertGetId([
            'prix' => 10000,
            'minute' => 60,
            'id_utilisateur' => $coiffeur,
            'id_speciale' => $specialite,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservation = Reservations::create([
            'numero_reservation' => 'RES-TEST-0001',
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'id_service' => $service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 11500,
            'methode_paiement' => 'stripe',
        ]);

        return Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'payment_intent_id' => 'pi_test_123',
            'amount' => 11500,
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'pending',
        ]);
    }

    private function signerPayload(string $payload): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_le_webhook_rejette_une_requete_sans_signature(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);

        $this->assertSame('pending', $paiement->fresh()->status);
        $this->assertSame('en_attente', Reservations::find($paiement->id_reservation)->statut_paiement);
    }

    public function test_le_webhook_rejette_une_signature_invalide(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1234567890,v1=signature_falsifiee',
        ], $payload);

        $response->assertStatus(400);

        $this->assertSame('pending', $paiement->fresh()->status);
    }

    public function test_le_webhook_confirme_le_paiement_avec_une_signature_valide(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload);

        $response->assertStatus(200);

        $this->assertSame('succeeded', $paiement->fresh()->status);
        $this->assertSame('paye', Reservations::find($paiement->id_reservation)->statut_paiement);
    }

    public function test_un_echec_de_paiement_laisse_la_reservation_non_payee(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();

        $payload = json_encode([
            'id' => 'evt_test_456',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload);

        $response->assertStatus(200);

        $this->assertSame('failed', $paiement->fresh()->status);
        $this->assertSame('echoue', Reservations::find($paiement->id_reservation)->statut_paiement);
    }

    private function notifier(Paiements $paiement, string $type): void
    {
        $payload = json_encode([
            'id' => 'evt_regression', 'type' => $type,
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);
        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload)->assertStatus(200);
    }

    public function test_relivraison_du_webhook_ne_cree_pas_deux_gains(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();
        $this->notifier($paiement, 'payment_intent.succeeded');
        $this->notifier($paiement, 'payment_intent.succeeded');
        $this->assertDatabaseCount('gains', 1);
    }

    public function test_changer_la_formule_ne_change_pas_le_gain_reserve(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();
        $reservation = Reservations::find($paiement->id_reservation);
        DB::table('users_app')->where('id_user_app', $reservation->id_coiffeur)
            ->update(['formule_abonnement' => 'premium']);
        $this->notifier($paiement, 'payment_intent.succeeded');
        $gain = DB::table('gains')->where('id_reservation', $reservation->id_reservation)->first();
        $this->assertEquals(10000, (float) $gain->montant_net);
        $this->assertEquals(1500, (float) $gain->montant_commission);
    }

    public function test_echec_d_une_autre_tentative_ne_declasse_pas_la_reservation_payee(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();
        $autre = Paiements::create([
            'id_reservation' => $paiement->id_reservation,
            'payment_intent_id' => 'pi_other_attempt', 'amount' => 11500,
            'currency' => 'XOF', 'payment_method' => 'stripe', 'status' => 'pending',
        ]);
        $this->notifier($paiement, 'payment_intent.succeeded');
        $this->notifier($autre, 'payment_intent.payment_failed');
        $this->assertSame('paye', Reservations::find($paiement->id_reservation)->statut_paiement);
        $this->assertDatabaseCount('gains', 1);
    }

    public function test_deux_notifications_de_succes_pour_une_reservation_ne_doublent_pas_le_gain(): void
    {
        $paiement = $this->creerReservationAvecPaiementPending();
        $autre = Paiements::create([
            'id_reservation' => $paiement->id_reservation,
            'payment_intent_id' => 'pi_second_success', 'amount' => 11500,
            'currency' => 'XOF', 'payment_method' => 'stripe', 'status' => 'pending',
        ]);
        $this->notifier($paiement, 'payment_intent.succeeded');
        $this->notifier($autre, 'payment_intent.succeeded');
        $this->assertDatabaseCount('gains', 1);
    }
}
