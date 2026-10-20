<?php

namespace Tests\Feature;

use App\Models\AbonnementPaiement;
use App\Models\Paiements;
use App\Models\UsersApp;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Paiement direct (carte/Mobile Money) pour passer à la formule Premium
 * immédiatement, sans attendre d'avoir des gains disponibles sur la
 * plateforme — contrairement au renouvellement mensuel automatique
 * (AbonnementService::preleverPour), qui reste inchangé et prélève sur le
 * solde de gains déjà perçus.
 */
class AbonnementPaiementDirectTest extends TestCase
{
    use RefreshDatabase;

    private function activerFauxStripe(): object
    {
        $faux = new class implements StripeGatewayInterface {
            public int $appelsCreate = 0;

            public function createPaymentIntent(float $montant): array
            {
                $this->appelsCreate++;

                return [
                    'id' => 'pi_abo_' . $this->appelsCreate,
                    'client_secret' => 'secret_abo_' . $this->appelsCreate,
                    'status' => 'requires_payment_method',
                ];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'client_secret' => 'secret_abo_1', 'status' => 'requires_payment_method'];
            }

            public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array
            {
                return ['id' => 're_fake', 'status' => 'succeeded'];
            }
        };

        $this->app->instance(StripeGatewayInterface::class, $faux);

        return $faux;
    }

    private function creerCoiffeuse(string $phone = '0700000070'): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => $phone,
            'password' => 'x', 'role' => 'hair',
        ]);
    }

    public function test_initie_un_paiement_stripe_pour_premium_sans_gains_disponibles(): void
    {
        $this->activerFauxStripe();
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $response = $this->postJson('/api/paiements/abonnement', ['formule' => 'premium']);

        $response->assertStatus(200)->assertJsonPath('client_secret', 'secret_abo_1');
        $this->assertDatabaseHas('abonnement_paiements', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => 'premium',
            'statut' => 'en_attente',
        ]);
        $this->assertSame('gratuit', $coiffeuse->fresh()->formule_abonnement ?? 'gratuit');
    }

    public function test_active_premium_quand_le_webhook_confirme_le_paiement(): void
    {
        $this->activerFauxStripe();
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/paiements/abonnement', ['formule' => 'premium'])->assertStatus(200);

        $paiement = Paiements::where('payment_intent_id', 'pi_abo_1')->firstOrFail();

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($this->payloadSucces($paiement)),
        ], $this->payloadSucces($paiement))->assertStatus(200);

        $coiffeuse->refresh();
        $this->assertSame('premium', $coiffeuse->formule_abonnement);
        $this->assertNotNull($coiffeuse->prochain_prelevement_le);
        $this->assertSame('reussi', AbonnementPaiement::first()->statut);
    }

    public function test_un_echec_de_paiement_naffecte_pas_la_formule(): void
    {
        $this->activerFauxStripe();
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/paiements/abonnement', ['formule' => 'premium'])->assertStatus(200);
        $paiement = Paiements::where('payment_intent_id', 'pi_abo_1')->firstOrFail();

        $payload = json_encode([
            'id' => 'evt_abo_echec', 'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);
        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload)->assertStatus(200);

        $this->assertSame('gratuit', $coiffeuse->fresh()->formule_abonnement ?? 'gratuit');
        $this->assertSame('echec', AbonnementPaiement::first()->statut);
    }

    public function test_une_nouvelle_tentative_reutilise_la_meme_demande_en_attente(): void
    {
        $this->activerFauxStripe();
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/paiements/abonnement', ['formule' => 'premium'])->assertStatus(200);
        $this->postJson('/api/paiements/abonnement', ['formule' => 'premium'])->assertStatus(200);

        $this->assertSame(1, AbonnementPaiement::count());
        $this->assertSame(1, Paiements::count());
    }

    public function test_refuse_la_formule_gratuite_qui_ne_necessite_aucun_paiement(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/paiements/abonnement', ['formule' => 'gratuit'])
            ->assertStatus(422);

        $this->assertSame(0, AbonnementPaiement::count());
    }

    public function test_le_statut_du_paiement_dabonnement_nest_consultable_que_par_son_titulaire(): void
    {
        $this->activerFauxStripe();
        $coiffeuse = $this->creerCoiffeuse();
        $autre = $this->creerCoiffeuse('0700000071');
        Sanctum::actingAs($coiffeuse);
        $this->postJson('/api/paiements/abonnement', ['formule' => 'premium'])->assertStatus(200);
        $abonnementPaiement = AbonnementPaiement::firstOrFail();

        Sanctum::actingAs($autre);
        $this->getJson("/api/paiements/abonnement/{$abonnementPaiement->id_abonnement_paiement}")
            ->assertStatus(404);

        Sanctum::actingAs($coiffeuse);
        $this->getJson("/api/paiements/abonnement/{$abonnementPaiement->id_abonnement_paiement}")
            ->assertStatus(200)
            ->assertJsonPath('data.statut_abonnement_paiement', 'en_attente');
    }

    private function payloadSucces(Paiements $paiement): string
    {
        return json_encode([
            'id' => 'evt_abo_succes', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);
    }

    private function signerPayload(string $payload): string
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

        return "t={$timestamp},v1={$signature}";
    }
}
