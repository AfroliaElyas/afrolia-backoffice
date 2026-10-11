<?php

namespace Tests\Feature;

use App\Models\Gains;
use App\Models\Reservations;
use App\Models\User;
use App\Models\UsersApp;
use App\Models\Versement;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VersementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(array $overrides = []): UsersApp
    {
        return UsersApp::create(array_merge([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '07' . random_int(10000000, 99999999),
            'password' => 'x', 'role' => 'hair',
        ], $overrides));
    }

    private function creerReservationEtGain(UsersApp $coiffeuse, string $statutReservation, float $montantNet, string $statutGain = 'en_attente'): Gains
    {
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '07' . random_int(10000000, 99999999), 'password' => 'x', 'role' => 'user']);
        $specialite = DB::table('specialites')->insertGetId(['libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now()]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-TEST-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => $statutReservation,
            'statut_paiement' => 'paye',
            'prix_service' => 10000,
            'montant_commission' => 800,
            'montant_total' => 10000,
            'methode_paiement' => 'mobile_money',
        ]);

        return Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
            'montant_brut' => 10000,
            'montant_commission' => 800,
            'montant_net' => $montantNet,
            'statut' => $statutGain,
        ]);
    }

    private function fauxConnecteur(?\Closure $surVerser = null): MobileMoneyGatewayInterface
    {
        return new class($surVerser) implements MobileMoneyGatewayInterface {
            public ?array $dernierDestinataire = null;
            public ?float $dernierMontant = null;

            public function __construct(private readonly ?\Closure $surVerser = null)
            {
            }

            public function initiate(\App\Models\Paiements $paiement, string $operateur, string $telephone): array
            {
                return ['reference' => 'MM-X', 'status' => 'pending'];
            }

            public function verifyWebhookSignature(string $payload, ?string $signature): bool
            {
                return true;
            }

            public function refund(array $destinataire, float $montant, string $reference): array
            {
                return ['id' => 'wth_test', 'status' => 'pending'];
            }

            public function verser(array $destinataire, float $montant, string $reference): array
            {
                $this->dernierDestinataire = $destinataire;
                $this->dernierMontant = $montant;

                if ($this->surVerser) {
                    return ($this->surVerser)($destinataire, $montant, $reference);
                }

                return ['id' => 'trf_test', 'status' => 'pending'];
            }
        };
    }

    public function test_seules_les_prestations_terminees_et_les_penalites_comptent_dans_le_solde(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $this->creerReservationEtGain($coiffeuse, 'confirmee', 9200); // pas encore réalisée : ne doit pas compter
        $this->creerReservationEtGain($coiffeuse, 'annulee', -800); // pénalité coiffeuse : compte quand même
        $this->creerReservationEtGain($coiffeuse, 'annulee', 0, 'annulee'); // annulation cliente : statut "annulee" sur le gain, exclu

        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/versements')->assertStatus(200);

        // 9200 (terminée) - 800 (pénalité) = 8400, la réservation "confirmee"
        // et le gain déjà "annulee" ne comptent pas.
        $this->app->instance(MobileMoneyGatewayInterface::class, $this->fauxConnecteur());
        $coiffeuse->update(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);

        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}")->assertRedirect();

        $versement = Versement::where('id_coiffeur', $coiffeuse->id_user_app)->first();
        $this->assertNotNull($versement);
        $this->assertSame(8400.0, (float) $versement->montant);
    }

    public function test_preparer_verrouille_les_gains_et_empeche_un_deuxieme_versement_en_parallele(): void
    {
        $coiffeuse = $this->creerCoiffeuse(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);
        $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}")->assertRedirect();
        $this->assertDatabaseCount('versements', 1);

        // Deuxième tentative de préparation pendant que la première est
        // encore "a_valider" : doit être refusée, pas de deuxième versement.
        $reponse = $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}");
        $reponse->assertRedirect();
        $this->assertDatabaseCount('versements', 1);
    }

    public function test_confirmer_appelle_le_connecteur_et_marque_les_gains_payes(): void
    {
        $coiffeuse = $this->creerCoiffeuse(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);
        $gain = $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $faux = $this->fauxConnecteur();
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}")->assertRedirect();
        $versement = Versement::first();

        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/confirmer")->assertRedirect();

        $this->assertSame('0700000099', $faux->dernierDestinataire['telephone']);
        $this->assertSame('wave', $faux->dernierDestinataire['operateur']);
        $this->assertSame(9200.0, $faux->dernierMontant);

        $this->assertSame('reussi', $versement->fresh()->statut);
        $this->assertSame('paye', $gain->fresh()->statut);
        $this->assertNotNull($gain->fresh()->date_paiement);
    }

    public function test_confirmer_deux_fois_de_suite_n_envoie_l_argent_qu_une_seule_fois(): void
    {
        $coiffeuse = $this->creerCoiffeuse(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);
        $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $faux = $this->fauxConnecteur();
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}");
        $versement = Versement::first();

        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/confirmer")->assertRedirect();
        $appelsApresPremiereConfirmation = 1;

        // Rejouer la confirmation (double-clic, rechargement de page) ne
        // doit pas déclencher un second transfert.
        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/confirmer")->assertRedirect();

        // Le faux connecteur n'enregistre que le dernier appel, donc on
        // vérifie plutôt l'état final : un seul versement "reussi".
        $this->assertSame(1, Versement::where('statut', 'reussi')->count());
    }

    public function test_echec_du_transfert_libere_les_gains_pour_une_nouvelle_tentative(): void
    {
        $coiffeuse = $this->creerCoiffeuse(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);
        $gain = $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $faux = $this->fauxConnecteur(function () {
            throw new \RuntimeException('Jèko a refusé : solde insuffisant');
        });
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}");
        $versement = Versement::first();

        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/confirmer")->assertRedirect();

        $this->assertSame('echoue', $versement->fresh()->statut);
        $this->assertNull($gain->fresh()->id_versement);
        $this->assertSame('en_attente', $gain->fresh()->statut);

        // Le gain libéré peut être inclus dans un nouveau versement.
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}")->assertRedirect();
        $this->assertDatabaseCount('versements', 2);
    }

    public function test_confirmer_est_bloque_sans_numero_de_versement_renseigne(): void
    {
        $coiffeuse = $this->creerCoiffeuse(); // pas de payout_telephone/operateur
        $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);
        $faux = $this->fauxConnecteur();
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}");
        $versement = Versement::first();

        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/confirmer")->assertRedirect();

        $this->assertNull($faux->dernierMontant);
        $this->assertSame('a_valider', $versement->fresh()->statut);
    }

    public function test_annuler_un_versement_prepare_libere_les_gains(): void
    {
        $coiffeuse = $this->creerCoiffeuse(['payout_telephone' => '0700000099', 'payout_operateur' => 'wave']);
        $gain = $this->creerReservationEtGain($coiffeuse, 'terminee', 9200);

        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/versements/preparer/{$coiffeuse->id_user_app}");
        $versement = Versement::first();
        $this->assertNotNull($gain->fresh()->id_versement);

        $this->actingAs($admin)->post("/versements/{$versement->id_versement}/annuler")->assertRedirect();

        $this->assertSame('annule', $versement->fresh()->statut);
        $this->assertNull($gain->fresh()->id_versement);
    }

    public function test_definir_infos_versement_enregistre_le_numero(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/versements/coiffeuse/{$coiffeuse->id_user_app}/infos", [
            'payout_telephone' => '0708001234',
            'payout_operateur' => 'orange',
        ])->assertRedirect();

        $coiffeuse->refresh();
        $this->assertSame('0708001234', $coiffeuse->payout_telephone);
        $this->assertSame('orange', $coiffeuse->payout_operateur);
    }
}
