<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripePaiementIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Faux connecteur Stripe : crée des PaymentIntent incrémentaux et permet
     * de piloter le statut renvoyé par retrievePaymentIntent() pour simuler
     * une tentative encore en cours, réussie ou annulée — sans jamais
     * appeler la vraie API Stripe.
     */
    private function activerFauxStripe(string $statutAuRetrieve = 'requires_payment_method'): void
    {
        $faux = new class($statutAuRetrieve) implements StripeGatewayInterface {
            public int $appelsCreate = 0;
            public int $appelsRetrieve = 0;

            public function __construct(private readonly string $statutAuRetrieve)
            {
            }

            public function createPaymentIntent(float $montant): array
            {
                $this->appelsCreate++;

                return [
                    'id' => 'pi_fake_' . $this->appelsCreate,
                    'client_secret' => 'secret_fake_' . $this->appelsCreate,
                    'status' => 'requires_payment_method',
                ];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                $this->appelsRetrieve++;

                return [
                    'id' => $paymentIntentId,
                    'client_secret' => 'secret_fake_1',
                    'status' => $this->statutAuRetrieve,
                ];
            }

            public function refund(string $paymentIntentId, float $montant): array
            {
                return ['id' => 're_fake', 'status' => 'succeeded'];
            }
        };

        $this->app->instance(StripeGatewayInterface::class, $faux);
    }

    private function creerReservation(): array
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000060', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000061', 'password' => 'x', 'role' => 'user',
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
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'en_attente',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => 'stripe',
        ]);

        return [$client, $reservation];
    }

    public function test_une_nouvelle_tentative_reutilise_le_paymentintent_en_cours(): void
    {
        $this->activerFauxStripe('requires_payment_method');
        [$client, $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);

        $body = ['montant' => 10500, 'id_reservation' => $reservation->id_reservation, 'methode' => 'stripe'];

        $premier = $this->postJson('/api/paiements', $body);
        $premier->assertStatus(200)->assertJsonPath('client_secret', 'secret_fake_1');

        $second = $this->postJson('/api/paiements', $body);
        $second->assertStatus(200)->assertJsonPath('client_secret', 'secret_fake_1');

        $this->assertDatabaseCount('paiements', 1);

        $fake = app(StripeGatewayInterface::class);
        $this->assertSame(1, $fake->appelsCreate);
        $this->assertSame(1, $fake->appelsRetrieve);
    }

    public function test_un_paymentintent_deja_reussi_ou_annule_n_est_pas_reutilise(): void
    {
        $this->activerFauxStripe('canceled');
        [$client, $reservation] = $this->creerReservation();
        Sanctum::actingAs($client);

        $body = ['montant' => 10500, 'id_reservation' => $reservation->id_reservation, 'methode' => 'stripe'];

        $this->postJson('/api/paiements', $body)->assertStatus(200);
        $this->postJson('/api/paiements', $body)->assertStatus(200);

        $this->assertDatabaseCount('paiements', 2);

        $fake = app(StripeGatewayInterface::class);
        $this->assertSame(2, $fake->appelsCreate);
    }
}
