<?php

namespace Tests\Feature;

use App\Models\AbonnementPaiement;
use App\Models\Gains;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse',
            'last_name' => 'Test',
            'phone' => '0700000001',
            'password' => 'x',
            'role' => 'hair',
        ]);
    }

    private function crediterGain(UsersApp $coiffeuse, float $montantNet): void
    {
        Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'montant_brut' => $montantNet,
            'montant_commission' => 0,
            'montant_net' => $montantNet,
            'statut' => 'disponible',
        ]);
    }

    public function test_une_nouvelle_coiffeuse_demarre_en_formule_gratuite(): void
    {
        $coiffeuse = $this->creerCoiffeuse();

        $this->assertSame('gratuit', $coiffeuse->fresh()->formule_abonnement);

        Sanctum::actingAs($coiffeuse);
        $this->getJson("/api/abonnement/{$coiffeuse->id_user_app}")
            ->assertStatus(200)
            ->assertJsonPath('data.formule_abonnement', 'gratuit')
            ->assertJsonPath('data.commission_actuelle', 5);
    }

    public function test_le_changement_vers_une_formule_payante_preleve_le_solde_disponible(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 5000);

        Sanctum::actingAs($coiffeuse);
        $response = $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'standard']);

        $response->assertStatus(200);

        $coiffeuse->refresh();
        $this->assertSame('standard', $coiffeuse->formule_abonnement);
        $this->assertNotNull($coiffeuse->prochain_prelevement_le);

        $this->assertDatabaseHas('abonnement_paiements', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => 'standard',
            'montant' => 4500,
            'statut' => 'reussi',
        ]);
    }

    public function test_le_changement_vers_une_formule_payante_echoue_si_le_solde_est_insuffisant(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 1000);

        Sanctum::actingAs($coiffeuse);
        $response = $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'standard']);

        $response->assertStatus(422);

        $coiffeuse->refresh();
        $this->assertSame('gratuit', $coiffeuse->formule_abonnement);
        $this->assertDatabaseHas('abonnement_paiements', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => 'standard',
            'statut' => 'echec',
        ]);
    }

    public function test_le_prelevement_mensuel_renouvelle_la_formule_si_le_solde_suffit(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 10000);

        $coiffeuse->update([
            'formule_abonnement' => 'premium',
            'prochain_prelevement_le' => now()->subDay(),
        ]);

        $this->artisan('abonnements:prelever')->assertExitCode(0);

        $coiffeuse->refresh();
        $this->assertSame('premium', $coiffeuse->formule_abonnement);
        $this->assertTrue($coiffeuse->prochain_prelevement_le->isFuture());
        $this->assertDatabaseHas('abonnement_paiements', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => 'premium',
            'montant' => 5500,
            'statut' => 'reussi',
        ]);
    }

    public function test_le_prelevement_mensuel_repasse_en_gratuit_si_le_solde_est_insuffisant(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 1000);

        $coiffeuse->update([
            'formule_abonnement' => 'standard',
            'prochain_prelevement_le' => now()->subDay(),
        ]);

        $this->artisan('abonnements:prelever')->assertExitCode(0);

        $coiffeuse->refresh();
        $this->assertSame('gratuit', $coiffeuse->formule_abonnement);
        $this->assertNull($coiffeuse->prochain_prelevement_le);
    }

    public function test_le_prelevement_mensuel_ignore_les_coiffeuses_pas_encore_a_echeance(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 10000);

        $coiffeuse->update([
            'formule_abonnement' => 'standard',
            'prochain_prelevement_le' => now()->addDays(10),
        ]);

        $this->artisan('abonnements:prelever')->assertExitCode(0);

        $this->assertSame(0, AbonnementPaiement::count());
    }

    public function test_la_commission_boutique_reflete_la_formule_de_la_coiffeuse(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->crediterGain($coiffeuse, 10000);
        Sanctum::actingAs($coiffeuse);
        $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'premium'])
            ->assertStatus(200);

        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000002', 'password' => 'x', 'role' => 'user',
        ]);

        $produit = \App\Models\Produits::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'nom' => 'Shampooing',
            'description' => 'Test',
            'prix' => 5000,
            'quantite_stock' => 10,
            'photo' => 'http://example.test/produit.jpg',
        ]);

        Sanctum::actingAs($client);
        $response = $this->postJson('/api/commandes', [
            'id_coiffeur' => $coiffeuse->id_user_app,
            'methode_paiement' => 'stripe',
            'lignes' => [['id_produit' => $produit->id_produit, 'quantite' => 1]],
        ]);

        $response->assertStatus(201);
        // Formule Premium => commission de 0 %.
        $response->assertJsonPath('data.montant_commission', 0);
        $response->assertJsonPath('data.montant_total', 5000);
    }
}
