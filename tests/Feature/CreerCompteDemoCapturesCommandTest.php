<?php

namespace Tests\Feature;

use App\Console\Commands\CreerCompteDemoCapturesCommand;
use App\Models\ClientFavorite;
use App\Models\Commandes;
use App\Models\Gains;
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

    public function test_peuple_un_favori_client_et_des_gains_disponibles(): void
    {
        // Sans ces deux éléments, les écrans "favoris" (client) et "revenus"
        // (coiffeuse) des captures d'écran store restent vides malgré un
        // compte par ailleurs peuplé (ajouté le 10/11/2026).
        $this->artisan('demo:creer-compte-captures')->assertSuccessful();

        $coiffeuse = UsersApp::where('phone', CreerCompteDemoCapturesCommand::TELEPHONE_COIFFEUSE)->first();
        $client = UsersApp::where('phone', CreerCompteDemoCapturesCommand::TELEPHONE_CLIENT)->first();

        $this->assertTrue(
            ClientFavorite::where('client_id', $client->id_user_app)
                ->where('stylist_id', $coiffeuse->id_user_app)
                ->exists()
        );

        // "en_attente" est explicitement exclu de tous les totaux affichés
        // par ApiGainsCoiffeuseController et du solde de
        // ApiDashboardCoiffeuseController : seul "disponible" (ou "paye")
        // apparaît réellement à l'écran.
        $reservation = Reservations::where('numero_reservation', 'like', 'DEMO-CAPTURES-%')->first();
        $gainReservation = Gains::where('id_reservation', $reservation->id_reservation)->first();
        $this->assertNotNull($gainReservation);
        $this->assertSame('disponible', $gainReservation->statut);
        $this->assertSame((float) $reservation->prix_service - $reservation->montant_commission, (float) $gainReservation->montant_net);

        $commande = Commandes::where('numero_commande', 'like', 'DEMO-CAPTURES-%')->first();
        $gainCommande = Gains::where('id_commande', $commande->id_commande)->first();
        $this->assertNotNull($gainCommande);
        $this->assertSame('disponible', $gainCommande->statut);
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
        $this->assertSame(1, ClientFavorite::count());
        $this->assertSame(2, Gains::count());
    }
}
