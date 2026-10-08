<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\Reservations;
use App\Models\User;
use App\Models\UsersApp;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemboursementStripeTest extends TestCase
{
    use RefreshDatabase;

    private function creerReservationAnnuleeEtPayee(string $methode = 'stripe'): Reservations
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000070', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000071', 'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'commission' => 500,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $reservation = Reservations::create([
            'numero_reservation' => 'RES-TEST-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'annulee',
            'statut_paiement' => 'paye',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => $methode,
        ]);

        Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'payment_intent_id' => $methode === 'stripe' ? 'pi_test_remboursement' : null,
            'amount' => 10500,
            'currency' => 'XOF',
            'payment_method' => $methode,
            'status' => 'succeeded',
        ]);

        return $reservation;
    }

    public function test_traiter_rembourse_reellement_sur_stripe(): void
    {
        $faux = new class implements StripeGatewayInterface {
            public ?string $dernierPaymentIntent = null;
            public ?float $dernierMontant = null;

            public function createPaymentIntent(float $montant): array
            {
                return ['id' => 'pi_x', 'client_secret' => 'secret_x', 'status' => 'requires_payment_method'];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'client_secret' => 'secret_x', 'status' => 'succeeded'];
            }

            public function refund(string $paymentIntentId, float $montant): array
            {
                $this->dernierPaymentIntent = $paymentIntentId;
                $this->dernierMontant = $montant;

                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('pi_test_remboursement', $faux->dernierPaymentIntent);
        $this->assertSame(10500.0, $faux->dernierMontant);
        $this->assertSame('rembourse', $reservation->fresh()->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'refunded',
        ]);
    }

    public function test_un_echec_stripe_ne_marque_pas_le_remboursement_comme_fait(): void
    {
        $faux = new class implements StripeGatewayInterface {
            public function createPaymentIntent(float $montant): array
            {
                return ['id' => 'pi_x', 'client_secret' => 'secret_x', 'status' => 'requires_payment_method'];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'client_secret' => 'secret_x', 'status' => 'succeeded'];
            }

            public function refund(string $paymentIntentId, float $montant): array
            {
                throw new \RuntimeException('carte expirée, remboursement refusé par Stripe');
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);
    }

    public function test_un_paiement_mobile_money_n_est_pas_rembourse_automatiquement(): void
    {
        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('mobile_money');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);
    }
}
