<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebloquerRemboursementsEnCoursTest extends TestCase
{
    use RefreshDatabase;

    private static int $compteur = 0;

    private function creerReservation(string $statutRemboursement, \Carbon\Carbon $misAJourLe): Reservations
    {
        self::$compteur++;

        $coiffeuse = UsersApp::create(['name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '070000' . str_pad((string) (self::$compteur * 2), 4, '0', STR_PAD_LEFT), 'password' => 'x', 'role' => 'hair']);
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '070000' . str_pad((string) (self::$compteur * 2 + 1), 4, '0', STR_PAD_LEFT), 'password' => 'x', 'role' => 'user']);
        $specialite = DB::table('specialites')->insertGetId(['libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now()]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'annulee',
            'statut_paiement' => 'paye',
            'statut_remboursement' => $statutRemboursement,
            'annule_par' => 'client',
            'prix_service' => 10000,
            'montant_commission' => 1234,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
        ]);

        // Contourne la gestion automatique des timestamps d'Eloquent (qui
        // écraserait toute valeur explicite d'updated_at passée à update())
        // pour pouvoir simuler un dossier resté "en_cours" depuis longtemps.
        DB::table('reservations')->where('id_reservation', $reservation->id_reservation)->update([
            'updated_at' => $misAJourLe,
        ]);

        return $reservation->fresh();
    }

    public function test_un_dossier_en_cours_depuis_longtemps_est_debloque(): void
    {
        $reservation = $this->creerReservation('en_cours', now()->subMinutes(30));

        $this->artisan('remboursements:debloquer-en-cours')->assertExitCode(0);

        $reservation->refresh();
        $this->assertSame('en_attente', $reservation->statut_remboursement);
        $historique = json_decode($reservation->remboursement_historique, true);
        $this->assertSame('debloque_sans_confirmation', end($historique)['action']);
        $this->assertStringContainsString('VÉRIFIER MANUELLEMENT', end($historique)['detail']);
    }

    public function test_un_dossier_en_cours_depuis_peu_nest_pas_touche(): void
    {
        $reservation = $this->creerReservation('en_cours', now()->subMinutes(2));

        $this->artisan('remboursements:debloquer-en-cours')->assertExitCode(0);

        $this->assertSame('en_cours', $reservation->fresh()->statut_remboursement);
    }

    public function test_les_dossiers_resolus_ne_sont_pas_touches(): void
    {
        $traite = $this->creerReservation('traite', now()->subHours(2));
        $enAttente = $this->creerReservation('en_attente', now()->subHours(2));
        $rejete = $this->creerReservation('rejete', now()->subHours(2));

        $this->artisan('remboursements:debloquer-en-cours')->assertExitCode(0);

        $this->assertSame('traite', $traite->fresh()->statut_remboursement);
        $this->assertSame('en_attente', $enAttente->fresh()->statut_remboursement);
        $this->assertSame('rejete', $rejete->fresh()->statut_remboursement);
    }
}
