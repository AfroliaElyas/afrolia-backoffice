<?php

namespace Tests\Feature;

use App\Console\Commands\PreleverAbonnementsMensuels;
use App\Models\AbonnementPaiement;
use App\Models\Gains;
use App\Models\UsersApp;
use App\Services\AbonnementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les jours déjà payés d'un abonnement Premium ne doivent plus être perdus
 * lors d'un changement de formule (demandé le 04/11/2026, suite à l'audit
 * financier qui a trouvé le bug : voir AbonnementService::preleverPour et
 * activerPeriode).
 */
class AbonnementProrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function creerCoiffeusePremium(Carbon $prochainPrelevement, bool $renouvellementAutomatique = true): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000090',
            'password' => 'x', 'role' => 'hair',
            'formule_abonnement' => 'premium',
            'prochain_prelevement_le' => $prochainPrelevement,
            'renouvellement_automatique' => $renouvellementAutomatique,
        ]);
    }

    private function creerCoiffeuseGratuite(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000091',
            'password' => 'x', 'role' => 'hair',
        ]);
    }

    private function donnerDuSolde(UsersApp $coiffeuse, float $montant): void
    {
        Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'montant_brut' => $montant,
            'montant_commission' => 0,
            'montant_net' => $montant,
            'statut' => 'disponible',
        ]);
    }

    public function test_passer_de_premium_a_gratuit_avec_des_jours_restants_ne_fait_rien_tout_de_suite(): void
    {
        $echeance = now()->addDays(20);
        $coiffeuse = $this->creerCoiffeusePremium($echeance);
        Sanctum::actingAs($coiffeuse);

        $response = $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'gratuit']);

        $response->assertStatus(200);
        $coiffeuse->refresh();
        $this->assertSame('premium', $coiffeuse->formule_abonnement);
        $this->assertSame($echeance->format('Y-m-d H:i:s'), $coiffeuse->prochain_prelevement_le->format('Y-m-d H:i:s'));
        $this->assertFalse($coiffeuse->renouvellement_automatique);
    }

    public function test_la_tache_planifiee_repasse_en_gratuite_a_lecheance_sans_creer_dechec_ni_toucher_au_solde(): void
    {
        Carbon::setTestNow(now());
        $coiffeuse = $this->creerCoiffeusePremium(now()->subMinute(), renouvellementAutomatique: false);
        $this->donnerDuSolde($coiffeuse, 100000);

        $this->artisan(PreleverAbonnementsMensuels::class)->assertSuccessful();

        $coiffeuse->refresh();
        $this->assertSame('gratuit', $coiffeuse->formule_abonnement);
        $this->assertNull($coiffeuse->prochain_prelevement_le);
        $this->assertTrue($coiffeuse->renouvellement_automatique);
        $this->assertSame(0, AbonnementPaiement::count());
        $this->assertSame(100000.0, (new AbonnementService())->soldeDisponible($coiffeuse->id_user_app));
    }

    public function test_annuler_un_passage_a_gratuite_deja_programme_ne_preleve_rien(): void
    {
        $echeance = now()->addDays(15);
        $coiffeuse = $this->creerCoiffeusePremium($echeance, renouvellementAutomatique: false);
        $this->donnerDuSolde($coiffeuse, 100000);
        Sanctum::actingAs($coiffeuse);

        $response = $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'premium']);

        $response->assertStatus(200);
        $coiffeuse->refresh();
        $this->assertTrue($coiffeuse->renouvellement_automatique);
        $this->assertSame($echeance->format('Y-m-d H:i:s'), $coiffeuse->prochain_prelevement_le->format('Y-m-d H:i:s'));
        $this->assertSame(0, AbonnementPaiement::count());
    }

    public function test_passer_de_gratuit_a_premium_preleve_le_solde_normalement(): void
    {
        Carbon::setTestNow(now());
        $coiffeuse = $this->creerCoiffeuseGratuite();
        $this->donnerDuSolde($coiffeuse, 100000);
        Sanctum::actingAs($coiffeuse);

        $response = $this->putJson("/api/abonnement/{$coiffeuse->id_user_app}", ['formule' => 'premium']);

        $response->assertStatus(200);
        $coiffeuse->refresh();
        $this->assertSame('premium', $coiffeuse->formule_abonnement);
        $this->assertTrue($coiffeuse->renouvellement_automatique);
        $this->assertSame(
            now()->addMonthNoOverflow()->format('Y-m-d H:i:s'),
            $coiffeuse->prochain_prelevement_le->format('Y-m-d H:i:s'),
        );
        $this->assertSame('reussi', AbonnementPaiement::first()->statut);
    }

    public function test_paiement_direct_par_carte_avec_des_jours_premium_restants_prolonge_depuis_lecheance(): void
    {
        $echeance = now()->addDays(10);
        $coiffeuse = $this->creerCoiffeusePremium($echeance);

        (new AbonnementService())->activerPeriode($coiffeuse->fresh(), 'premium');

        $coiffeuse->refresh();
        $this->assertSame(
            $echeance->copy()->addMonthNoOverflow()->format('Y-m-d H:i:s'),
            $coiffeuse->prochain_prelevement_le->format('Y-m-d H:i:s'),
        );
        $this->assertTrue($coiffeuse->renouvellement_automatique);
    }

    public function test_solde_insuffisant_repasse_en_gratuite_et_reinitialise_le_renouvellement(): void
    {
        Carbon::setTestNow(now());
        $coiffeuse = $this->creerCoiffeusePremium(now()->subMinute(), renouvellementAutomatique: true);

        $this->artisan(PreleverAbonnementsMensuels::class)->assertSuccessful();

        $coiffeuse->refresh();
        $this->assertSame('gratuit', $coiffeuse->formule_abonnement);
        $this->assertNull($coiffeuse->prochain_prelevement_le);
        $this->assertTrue($coiffeuse->renouvellement_automatique);
        $this->assertSame('echec', AbonnementPaiement::first()->statut);
    }
}
