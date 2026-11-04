<?php

namespace Tests\Feature;

use App\Models\CommandeLignes;
use App\Models\Commandes;
use App\Models\Gains;
use App\Models\Paiements;
use App\Models\Produits;
use App\Models\User;
use App\Models\UsersApp;
use App\Services\MobileMoney\JekoGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Remboursement des commandes boutique déjà payées (demandé le 04/11/2026,
 * suite à l'audit financier : aucun moyen n'existait de rembourser une
 * commande déjà payée). Deux étapes distinctes, comme pour les
 * réservations : l'API ouvre le dossier (ApiCommandesController), le
 * back-office exécute le remboursement réel (RemboursementCommandeController).
 */
class RemboursementCommandeTest extends TestCase
{
    use RefreshDatabase;

    private function creerCommandePayee(): array
    {
        $coiffeuse = UsersApp::create(['name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000070', 'password' => 'x', 'role' => 'hair']);
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000071', 'password' => 'x', 'role' => 'user']);

        $produit = Produits::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'nom' => 'Shampoing', 'prix' => 5000, 'quantite_stock' => 3, 'statut' => 'actif',
        ]);

        $commande = Commandes::create([
            'numero_commande' => 'CMD-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'montant_produits' => 10000,
            'montant_commission' => 800,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
            'statut_paiement' => 'paye',
            'statut_commande' => 'payee',
        ]);

        CommandeLignes::create([
            'id_commande' => $commande->id_commande,
            'id_produit' => $produit->id_produit,
            'nom_produit' => $produit->nom,
            'prix_unitaire' => 5000,
            'quantite' => 2,
            'sous_total' => 10000,
        ]);

        Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_commande' => $commande->id_commande,
            'montant_brut' => 10000,
            'montant_commission' => 800,
            'montant_net' => 9200,
            'statut' => 'en_attente',
        ]);

        Paiements::create([
            'id_commande' => $commande->id_commande,
            'payment_intent_id' => 'pi_test_commande',
            'amount' => 10000,
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
        ]);

        return compact('coiffeuse', 'client', 'produit', 'commande');
    }

    public function test_la_cliente_annule_sa_commande_payee_et_ouvre_le_dossier(): void
    {
        ['client' => $client, 'produit' => $produit, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/commandes/{$commande->id_commande}/annuler", ['raison' => 'Changement d\'avis']);

        $response->assertStatus(200);
        $commande->refresh();
        $this->assertSame('annulee', $commande->statut_commande);
        $this->assertSame('paye', $commande->statut_paiement); // pas encore remboursé, juste le dossier ouvert
        $this->assertSame('en_attente', $commande->statut_remboursement);
        $this->assertSame(5, $produit->fresh()->quantite_stock); // 3 restant + 2 restitués
        $this->assertSame('annulee', Gains::where('id_commande', $commande->id_commande)->first()->statut);
        $this->assertSame(0.0, Gains::where('id_commande', $commande->id_commande)->first()->montant_net);
    }

    public function test_la_coiffeuse_refuse_une_commande_payee_et_ouvre_le_dossier(): void
    {
        ['coiffeuse' => $coiffeuse, 'produit' => $produit, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($coiffeuse);

        $response = $this->postJson("/api/commandes/{$commande->id_commande}/refuser", ['raison' => 'Rupture de stock']);

        $response->assertStatus(200);
        $commande->refresh();
        $this->assertSame('annulee', $commande->statut_commande);
        $this->assertSame('en_attente', $commande->statut_remboursement);
        $this->assertSame(5, $produit->fresh()->quantite_stock);
    }

    public function test_impossible_dannuler_une_commande_deja_expediee(): void
    {
        ['client' => $client, 'commande' => $commande] = $this->creerCommandePayee();
        $commande->update(['statut_commande' => 'expediee', 'expediee_le' => now()]);
        Sanctum::actingAs($client);

        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")
            ->assertStatus(400);

        $this->assertSame('expediee', $commande->fresh()->statut_commande);
        $this->assertSame('aucun', $commande->fresh()->statut_remboursement);
    }

    public function test_impossible_dannuler_une_commande_deja_livree(): void
    {
        ['client' => $client, 'commande' => $commande] = $this->creerCommandePayee();
        $commande->update(['statut_commande' => 'livree', 'livree_le' => now()]);
        Sanctum::actingAs($client);

        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")
            ->assertStatus(400);
    }

    public function test_quelquun_dautre_ne_peut_pas_annuler_la_commande(): void
    {
        ['commande' => $commande] = $this->creerCommandePayee();
        $autre = UsersApp::create(['name' => 'Autre', 'last_name' => 'Client', 'phone' => '0700000099', 'password' => 'x', 'role' => 'user']);
        Sanctum::actingAs($autre);

        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")
            ->assertStatus(404);

        $this->assertSame('payee', $commande->fresh()->statut_commande);
    }

    public function test_annuler_deux_fois_de_suite_ne_restitue_le_stock_quune_seule_fois(): void
    {
        ['client' => $client, 'produit' => $produit, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($client);

        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")->assertStatus(200);
        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")->assertStatus(400);

        $this->assertSame(5, $produit->fresh()->quantite_stock);
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

            public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array
            {
                $this->dernierPaymentIntent = $paymentIntentId;
                $this->dernierMontant = $montant;

                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        ['client' => $client, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($client);
        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")->assertStatus(200);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements-commandes/{$commande->id_commande}/traiter")
            ->assertRedirect();

        $this->assertSame('pi_test_commande', $faux->dernierPaymentIntent);
        $this->assertSame(10000.0, $faux->dernierMontant);
        $commande->refresh();
        $this->assertSame('rembourse', $commande->statut_paiement);
        $this->assertSame('traite', $commande->statut_remboursement);
        $this->assertDatabaseHas('paiements', ['id_commande' => $commande->id_commande, 'status' => 'refunded']);
    }

    public function test_traiter_deux_fois_de_suite_ne_rembourse_pas_deux_fois(): void
    {
        $appels = 0;
        $faux = new class($appels) implements StripeGatewayInterface {
            public function __construct(private int &$appels)
            {
            }

            public function createPaymentIntent(float $montant): array
            {
                return ['id' => 'pi_x', 'client_secret' => 'secret_x', 'status' => 'requires_payment_method'];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'client_secret' => 'secret_x', 'status' => 'succeeded'];
            }

            public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array
            {
                $this->appels++;

                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        ['client' => $client, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($client);
        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")->assertStatus(200);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/remboursements-commandes/{$commande->id_commande}/traiter")->assertRedirect();
        $this->actingAs($admin)
            ->post("/remboursements-commandes/{$commande->id_commande}/traiter")
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(1, $appels);
    }

    public function test_un_echec_stripe_ne_marque_pas_le_remboursement_comme_fait_et_permet_de_reessayer(): void
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

            public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array
            {
                throw new \RuntimeException('carte expirée, remboursement refusé par Stripe');
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        ['client' => $client, 'commande' => $commande] = $this->creerCommandePayee();
        Sanctum::actingAs($client);
        $this->postJson("/api/commandes/{$commande->id_commande}/annuler")->assertStatus(200);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements-commandes/{$commande->id_commande}/traiter")
            ->assertRedirect();

        $commande->refresh();
        $this->assertSame('paye', $commande->statut_paiement);
        $this->assertSame('en_attente', $commande->statut_remboursement);
        $this->assertDatabaseHas('paiements', ['id_commande' => $commande->id_commande, 'status' => 'succeeded']);
    }

    public function test_traiter_sans_dossier_en_attente_ne_fait_rien(): void
    {
        ['commande' => $commande] = $this->creerCommandePayee();
        // Jamais annulée : aucun dossier de remboursement à traiter.
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post("/remboursements-commandes/{$commande->id_commande}/traiter")
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('payee', $commande->fresh()->statut_commande);
    }

    private const SECRET_WEBHOOK = 'secret_webhook_test_commande';

    private function envoyerWebhookJeko(array $corps): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($corps);
        $signature = hash_hmac('sha256', $payload, self::SECRET_WEBHOOK);

        return $this->call('POST', '/api/mobile-money/webhook', [], [], [], [
            'HTTP_Jeko-Event' => 'TRANSACTION_COMPLETED',
            'HTTP_Jeko-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_un_webhook_de_succes_confirme_le_remboursement_dune_commande(): void
    {
        $this->app->instance(MobileMoneyGatewayInterface::class, new JekoGateway(
            apiKey: 'x', apiKeyId: 'x', storeId: 'x', webhookSecret: self::SECRET_WEBHOOK,
        ));

        ['commande' => $commande] = $this->creerCommandePayee();
        $commande->update([
            'statut_commande' => 'annulee',
            'statut_remboursement' => 'en_cours',
            'remboursement_reference' => 'REMB-COMMANDE-999',
            'methode_paiement' => 'mobile_money',
        ]);
        Paiements::where('id_commande', $commande->id_commande)->update([
            'payment_method' => 'mobile_money',
            'mobile_money_operateur' => 'wave',
            'mobile_money_telephone' => '+2250701234567',
        ]);

        $this->envoyerWebhookJeko([
            'id' => 'jeko_tx_commande_1',
            'status' => 'success',
            'transactionDetails' => ['reference' => 'REMB-COMMANDE-999'],
            'amount' => ['amount' => 10000, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);

        $commande->refresh();
        $this->assertSame('traite', $commande->statut_remboursement);
        $this->assertSame('rembourse', $commande->statut_paiement);
        $this->assertDatabaseHas('paiements', ['id_commande' => $commande->id_commande, 'status' => 'refunded']);
    }

    public function test_un_webhook_dechec_remet_le_dossier_commande_en_attente(): void
    {
        $this->app->instance(MobileMoneyGatewayInterface::class, new JekoGateway(
            apiKey: 'x', apiKeyId: 'x', storeId: 'x', webhookSecret: self::SECRET_WEBHOOK,
        ));

        ['commande' => $commande] = $this->creerCommandePayee();
        $commande->update([
            'statut_commande' => 'annulee',
            'statut_remboursement' => 'en_cours',
            'remboursement_reference' => 'REMB-COMMANDE-998',
            'methode_paiement' => 'mobile_money',
        ]);

        $this->envoyerWebhookJeko([
            'id' => 'jeko_tx_commande_2',
            'status' => 'error',
            'transactionDetails' => ['reference' => 'REMB-COMMANDE-998'],
            'amount' => ['amount' => 10000, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);

        $commande->refresh();
        $this->assertSame('en_attente', $commande->statut_remboursement);
        $this->assertSame('paye', $commande->statut_paiement);
    }
}
