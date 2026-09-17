<?php

namespace Tests\Feature;

use App\Models\Commandes;
use App\Models\Paiements;
use App\Models\Produits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoutiqueTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    private function creerUtilisateur(string $phone, string $role): int
    {
        return DB::table('users_app')->insertGetId([
            'name' => 'Test',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => 'x',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function creerProduit(int $idCoiffeur, float $prix = 5000, int $stock = 10): Produits
    {
        return Produits::create([
            'id_coiffeur' => $idCoiffeur,
            'nom' => 'Shampooing',
            'description' => 'Test',
            'prix' => $prix,
            'quantite_stock' => $stock,
            'photo' => 'http://example.test/produit.jpg',
        ]);
    }

    private function signerPayload(string $payload): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_le_checkout_decremente_le_stock_et_calcule_la_commission(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur, prix: 5000, stock: 10);

        $response = $this->postJson('/api/commandes', [
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'methode_paiement' => 'stripe',
            'lignes' => [
                ['id_produit' => $produit->id_produit, 'quantite' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.montant_produits', 10000);
        $response->assertJsonPath('data.montant_commission', 1500);
        $response->assertJsonPath('data.montant_total', 11500);

        $this->assertSame(8, $produit->fresh()->quantite_stock);
    }

    public function test_le_checkout_echoue_si_le_stock_est_insuffisant(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur, prix: 5000, stock: 1);

        $response = $this->postJson('/api/commandes', [
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'methode_paiement' => 'stripe',
            'lignes' => [
                ['id_produit' => $produit->id_produit, 'quantite' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, $produit->fresh()->quantite_stock);
        $this->assertSame(0, Commandes::count());
    }

    public function test_le_paiement_reussi_marque_la_commande_payee(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur);

        $commande = $this->postJson('/api/commandes', [
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'methode_paiement' => 'stripe',
            'lignes' => [['id_produit' => $produit->id_produit, 'quantite' => 1]],
        ])->json('data');

        $paiement = Paiements::create([
            'id_commande' => $commande['id_commande'],
            'payment_intent_id' => 'pi_commande_123',
            'amount' => $commande['montant_total'],
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        $payload = json_encode([
            'id' => 'evt_commande_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload);

        $response->assertStatus(200);

        $commandeFraiche = Commandes::find($commande['id_commande']);
        $this->assertSame('paye', $commandeFraiche->statut_paiement);
        $this->assertSame('payee', $commandeFraiche->statut_commande);
        $this->assertDatabaseHas('gains', ['id_commande' => $commande['id_commande']]);
    }

    public function test_un_paiement_echoue_annule_la_commande_et_restitue_le_stock(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur, prix: 5000, stock: 10);

        $commande = $this->postJson('/api/commandes', [
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'methode_paiement' => 'stripe',
            'lignes' => [['id_produit' => $produit->id_produit, 'quantite' => 3]],
        ])->json('data');

        $this->assertSame(7, $produit->fresh()->quantite_stock);

        $paiement = Paiements::create([
            'id_commande' => $commande['id_commande'],
            'payment_intent_id' => 'pi_commande_456',
            'amount' => $commande['montant_total'],
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        $payload = json_encode([
            'id' => 'evt_commande_2',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => $paiement->payment_intent_id]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signerPayload($payload),
        ], $payload);

        $response->assertStatus(200);

        $this->assertSame(10, $produit->fresh()->quantite_stock);

        $commandeFraiche = Commandes::find($commande['id_commande']);
        $this->assertSame('echoue', $commandeFraiche->statut_paiement);
        $this->assertSame('annulee', $commandeFraiche->statut_commande);
    }

    public function test_expedier_puis_livrer_suivent_le_cycle_de_vie(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur);

        $commande = Commandes::create([
            'numero_commande' => 'CMD-TEST-0001',
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'montant_produits' => 5000,
            'montant_commission' => 750,
            'montant_total' => 5750,
            'methode_paiement' => 'stripe',
        ]);

        // Ne peut pas être expédiée avant d'être payée.
        $this->putJson("/api/commandes/expedier/{$commande->id_commande}", ['id_coiffeur' => $coiffeur])
            ->assertStatus(400);

        $commande->update(['statut_commande' => 'payee', 'statut_paiement' => 'paye']);

        $this->putJson("/api/commandes/expedier/{$commande->id_commande}", ['id_coiffeur' => $coiffeur])
            ->assertStatus(200)
            ->assertJsonPath('data.statut_commande', 'expediee');

        $this->putJson("/api/commandes/livrer/{$commande->id_commande}", ['id_coiffeur' => $coiffeur])
            ->assertStatus(200)
            ->assertJsonPath('data.statut_commande', 'livree');
    }

    public function test_la_commande_annuler_expirees_restitue_le_stock(): void
    {
        $coiffeur = $this->creerUtilisateur('0711111111', 'hair');
        $client = $this->creerUtilisateur('0722222222', 'user');
        $produit = $this->creerProduit($coiffeur, prix: 5000, stock: 10);

        $commande = Commandes::create([
            'numero_commande' => 'CMD-TEST-0002',
            'id_client' => $client,
            'id_coiffeur' => $coiffeur,
            'montant_produits' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 11500,
            'methode_paiement' => 'stripe',
        ]);
        $commande->lignes()->create([
            'id_produit' => $produit->id_produit,
            'nom_produit' => $produit->nom,
            'prix_unitaire' => $produit->prix,
            'quantite' => 2,
        ] + ['sous_total' => 10000]);
        $produit->decrement('quantite_stock', 2);
        $commande->forceFill(['created_at' => now()->subHour()])->save();

        $this->artisan('commandes:annuler-expirees', ['--minutes' => 30])
            ->assertExitCode(0);

        $this->assertSame(10, $produit->fresh()->quantite_stock);
        $this->assertSame('annulee', $commande->fresh()->statut_commande);
    }
}
