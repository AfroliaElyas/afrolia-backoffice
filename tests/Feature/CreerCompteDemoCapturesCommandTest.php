<?php

namespace Tests\Feature;

use App\Console\Commands\CreerCompteDemoCapturesCommand;
use App\Models\Commandes;
use App\Models\Produits;
use App\Models\Reservations;
use App\Models\Reviews;
use App\Models\Services;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreerCompteDemoCapturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_le_compte_coiffeuse_et_le_compte_client(): void
    {
        $this->artisan('demo:creer-compte-captures')->assertSuccessful();

        $coiffeuse = UsersApp::where('phone', CreerCompteDemoCapturesCommand::TELEPHONE_COIFFEUSE)->first();
        $client = UsersApp::where('phone', CreerCompteDemoCapturesCommand::TELEPHONE_CLIENT)->first();

        $this->assertNotNull($coiffeuse);
        $this->assertSame('hair', $coiffeuse->role);
        $this->assertSame('Active', $coiffeuse->statut);
        $this->assertTrue(Hash::check(CreerCompteDemoCapturesCommand::MOT_DE_PASSE, $coiffeuse->password));

        $this->assertNotNull($client);
        $this->assertSame('user', $client->role);
        $this->assertSame('Active', $client->statut);
    }

    public function test_peuple_services_produits_reservation_et_avis(): void
    {
        $this->artisan('demo:creer-compte-captures')->assertSuccessful();

        $coiffeuse = UsersApp::where('phone', CreerCompteDemoCapturesCommand::TELEPHONE_COIFFEUSE)->first();

        $this->assertSame(3, Services::where('id_utilisateur', $coiffeuse->id_user_app)->count());
        $this->assertSame(3, Produits::where('id_coiffeur', $coiffeuse->id_user_app)->count());

        $reservation = Reservations::where('numero_reservation', 'like', 'DEMO-CAPTURES-%')->first();
        $this->assertNotNull($reservation);
        $this->assertSame('terminee', $reservation->statut);
        $this->assertSame('paye', $reservation->statut_paiement);

        $this->assertTrue(Reviews::where('id_reservation', $reservation->id_reservation)->exists());

        $commande = Commandes::where('numero_commande', 'like', 'DEMO-CAPTURES-%')->first();
        $this->assertNotNull($commande);
        $this->assertSame('livree', $commande->statut_commande);
        $this->assertSame(1, $commande->lignes()->count());
    }

    public function test_est_idempotente_quand_relancee_plusieurs_fois(): void
    {
        $this->artisan('demo:creer-compte-captures')->assertSuccessful();
        $this->artisan('demo:creer-compte-captures')
            ->assertSuccessful()
            ->expectsOutputToContain('déjà présent');

        $this->assertSame(2, UsersApp::count());
        $this->assertSame(3, Services::count());
        $this->assertSame(3, Produits::count());
        $this->assertSame(1, Reservations::count());
        $this->assertSame(1, Commandes::count());
        $this->assertSame(1, Reviews::count());
    }
}
