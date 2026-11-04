<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RappelsReservationCommandTest extends TestCase
{
    use RefreshDatabase;

    private function creerReservation(string $statut, Carbon $debut, ?Carbon $rappelDejaEnvoyeA = null): Reservations
    {
        static $compteur = 0;
        $compteur++;

        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => "070000{$compteur}0", 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => "070000{$compteur}1", 'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => $debut->toDateString(),
            'heure_reservation' => $debut->format('H:i:s'),
            'statut' => $statut,
            'prix_service' => 10000,
            'montant_commission' => 1500,
            'montant_total' => 10000,
            'methode_paiement' => 'stripe',
            'rappel_envoye_at' => $rappelDejaEnvoyeA,
        ]);
    }

    public function test_notifie_la_cliente_et_la_coiffeuse_pour_un_rendez_vous_dans_une_demi_heure(): void
    {
        $reservation = $this->creerReservation('confirmee', now()->addMinutes(30));

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->twice();

        $this->artisan('rappels:envoyer')->assertExitCode(0);

        $this->assertNotNull($reservation->fresh()->rappel_envoye_at);
    }

    public function test_ignore_un_rendez_vous_trop_eloigne(): void
    {
        $reservation = $this->creerReservation('confirmee', now()->addHours(3));

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->never();

        $this->artisan('rappels:envoyer');

        $this->assertNull($reservation->fresh()->rappel_envoye_at);
    }

    public function test_ignore_une_reservation_non_confirmee(): void
    {
        $this->creerReservation('en_attente', now()->addMinutes(30));

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->never();

        $this->artisan('rappels:envoyer');
    }

    public function test_ne_renvoie_jamais_un_rappel_deja_envoye(): void
    {
        $this->creerReservation('confirmee', now()->addMinutes(30), rappelDejaEnvoyeA: now()->subMinutes(5));

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->never();

        $this->artisan('rappels:envoyer');
    }

    public function test_deux_executions_successives_n_envoient_le_rappel_qu_une_seule_fois(): void
    {
        $this->creerReservation('confirmee', now()->addMinutes(30));

        $this->mock(NotificationService::class)
            ->shouldReceive('envoyerAUtilisateur')
            ->twice(); // au total sur les deux appels de la commande

        $this->artisan('rappels:envoyer');
        $this->artisan('rappels:envoyer');
    }
}
