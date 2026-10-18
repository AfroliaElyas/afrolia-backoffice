<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\Reservations;
use App\Models\User;
use App\Models\UsersApp;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemboursementStripeTest extends TestCase
{
    use RefreshDatabase;

    private function creerReservationAnnuleeEtPayee(string $methode = 'stripe', array $mobileMoney = [], string $annulePar = 'client'): Reservations
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000070', 'password' => 'x', 'role' => 'hair',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000071', 'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // La cliente paie le prix affiché (10 000), sans supplément : la
        // commission est une retenue sur les gains de la coiffeuse, jamais
        // un montant payé en plus.
        //
        // montant_commission = 1 234 : valeur arbitraire choisie pour ne
        // correspondre à AUCUN taux candidat (5 %, 8 %, 3 %, 6 % de 10 000),
        // volontairement pour ne pas laisser croire que ces tests valident
        // un taux précis — seul le calcul "prix payé moins cette retenue"
        // est testé ici, pas un pourcentage (les taux restent à confirmer,
        // voir la conversation du 18/10/2026).
        $reservation = Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => 'annulee',
            'statut_paiement' => 'paye',
            'statut_remboursement' => 'en_attente',
            'annule_par' => $annulePar,
            'prix_service' => 10000,
            'montant_commission' => 1234,
            'montant_total' => 10000,
            'methode_paiement' => $methode,
        ]);

        Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'payment_intent_id' => $methode === 'stripe' ? 'pi_test_remboursement' : null,
            'amount' => 10000,
            'currency' => 'XOF',
            'payment_method' => $methode,
            'mobile_money_operateur' => $mobileMoney['operateur'] ?? null,
            'mobile_money_telephone' => $mobileMoney['telephone'] ?? null,
            'status' => 'succeeded',
        ]);

        return $reservation;
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

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('pi_test_remboursement', $faux->dernierPaymentIntent);
        // Annulation par la cliente : elle supporte la commission Afrolia,
        // qui reste due (10000 - 1234 de commission, valeur arbitraire de
        // la fixture, indépendante du taux réel non encore confirmé).
        $this->assertSame(8766.0, $faux->dernierMontant);
        $this->assertSame('rembourse', $reservation->fresh()->statut_paiement);
        $this->assertSame('traite', $reservation->fresh()->statut_remboursement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'refunded',
        ]);
    }

    public function test_remboursement_integral_si_l_annulation_vient_de_la_coiffeuse(): void
    {
        $faux = new class implements StripeGatewayInterface {
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
                $this->dernierMontant = $montant;

                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe', [], 'coiffeur');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        // Annulation par la coiffeuse : la cliente ne supporte aucun frais,
        // elle est remboursée intégralement.
        $this->assertSame(10000.0, $faux->dernierMontant);
    }

    public function test_une_annulation_payee_apparait_dans_les_remboursements_a_traiter(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $attente = DB::table('reservations')
            ->where('statut', 'annulee')
            ->where('statut_paiement', 'paye')
            ->count();

        $this->assertSame(1, $attente);
        $this->assertDatabaseHas('reservations', [
            'id_reservation' => $reservation->id_reservation,
            'statut' => 'annulee',
            'statut_paiement' => 'paye',
        ]);
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

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        // Premier traitement : rembourse réellement.
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        // Deuxième clic (double traitement séquentiel) sur la même
        // réservation : ne doit plus trouver de paiement "succeeded" (déjà
        // passé à "refunded" par le premier appel), donc aucun deuxième
        // remboursement réel n'est envoyé.
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(1, $appels);
    }

    public function test_un_echec_stripe_ne_marque_pas_le_remboursement_comme_fait(): void
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

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
        // Après un échec, le dossier redescend en "en_attente" (jamais
        // "rejete" : un échec technique n'est pas un refus) pour qu'une
        // nouvelle tentative le retrouve dans la liste à traiter.
        $this->assertSame('en_attente', $reservation->fresh()->statut_remboursement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);
    }

    public function test_un_paiement_mobile_money_sans_numero_d_origine_n_est_pas_rembourse_automatiquement(): void
    {
        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('mobile_money');

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
        $this->assertSame('en_attente', $reservation->fresh()->statut_remboursement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);
    }

    public function test_un_paiement_mobile_money_est_reellement_rembourse_via_jeko(): void
    {
        $faux = new class implements MobileMoneyGatewayInterface {
            public ?array $dernierDestinataire = null;
            public ?float $dernierMontant = null;

            public function initiate(\App\Models\Paiements $paiement, string $operateur, string $telephone): array
            {
                return ['reference' => 'MM-X', 'status' => 'pending'];
            }

            public function verifyWebhookSignature(string $payload, ?string $signature): bool
            {
                return false;
            }

            public function refund(array $destinataire, float $montant, string $reference): array
            {
                $this->dernierDestinataire = $destinataire;
                $this->dernierMontant = $montant;

                return ['id' => 'wth_test', 'status' => 'pending'];
            }

            public function verser(array $destinataire, float $montant, string $reference): array
            {
                return ['id' => 'wth_test', 'status' => 'pending'];
            }
        };
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('mobile_money', [
            'operateur' => 'wave',
            'telephone' => '+2250701234567',
        ]);

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('+2250701234567', $faux->dernierDestinataire['telephone']);
        $this->assertSame('wave', $faux->dernierDestinataire['operateur']);
        // Annulation par la cliente (valeur par défaut du fixture) : elle
        // supporte la commission Afrolia (10000 - 1234, valeur arbitraire).
        $this->assertSame(8766.0, $faux->dernierMontant);
        $this->assertSame('rembourse', $reservation->fresh()->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'refunded',
        ]);
    }

    public function test_un_echec_du_transfert_jeko_ne_marque_pas_le_remboursement_comme_fait(): void
    {
        $faux = new class implements MobileMoneyGatewayInterface {
            public function initiate(\App\Models\Paiements $paiement, string $operateur, string $telephone): array
            {
                return ['reference' => 'MM-X', 'status' => 'pending'];
            }

            public function verifyWebhookSignature(string $payload, ?string $signature): bool
            {
                return false;
            }

            public function refund(array $destinataire, float $montant, string $reference): array
            {
                throw new \RuntimeException('Échec du transfert Jèko : 400 insufficient_balance');
            }

            public function verser(array $destinataire, float $montant, string $reference): array
            {
                throw new \RuntimeException('Échec du transfert Jèko : 400 insufficient_balance');
            }
        };
        $this->app->instance(MobileMoneyGatewayInterface::class, $faux);

        $admin = User::factory()->create();
        $reservation = $this->creerReservationAnnuleeEtPayee('mobile_money', [
            'operateur' => 'wave',
            'telephone' => '+2250701234567',
        ]);

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect();

        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
        $this->assertSame('en_attente', $reservation->fresh()->statut_remboursement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);
    }

    public function test_une_reservation_deja_en_cours_ne_peut_pas_etre_rejetee_par_un_autre_admin(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');
        $reservation->update(['statut_remboursement' => 'en_cours']);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/rejeter", ['motif' => 'doublon', 'details' => 'Test'])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('en_cours', $reservation->fresh()->statut_remboursement);
    }

    public function test_rejeter_enregistre_le_motif_et_lhistorique_sans_rembourser(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/rejeter", [
                'motif' => 'doublon',
                'details' => 'Doublon avec le remboursement RES-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $reservation->refresh();
        $this->assertSame('rejete', $reservation->statut_remboursement);
        $this->assertStringContainsString('Doublon avec le remboursement RES-123', $reservation->raison_rejet_remboursement);
        // Rejeter ne rembourse jamais : le paiement reste "succeeded", pas "refunded".
        $this->assertSame('paye', $reservation->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'succeeded',
        ]);

        $historique = json_decode($reservation->remboursement_historique, true);
        $this->assertNotEmpty($historique);
        $this->assertSame('rejete', end($historique)['action']);
    }

    public function test_un_motif_de_rejet_ordinaire_hors_liste_est_refuse_par_la_validation(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');

        $admin = User::factory()->create();
        // Aucun motif "ordinaire" (la cliente ou la coiffeuse a simplement
        // annulé) ne figure dans la liste fermée acceptée : la validation
        // doit refuser toute valeur hors de cette liste, pas seulement
        // exiger un texte non vide — c'est le blocage réel demandé le
        // 18/10/2026 (un commentaire et un champ obligatoire ne suffisaient
        // pas).
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/rejeter", [
                'motif' => 'la_cliente_a_annule',
                'details' => 'Remboursement non souhaité',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('motif');

        $this->assertSame('en_attente', $reservation->fresh()->statut_remboursement);
        $this->assertNull($reservation->fresh()->raison_rejet_remboursement);
    }

    public function test_un_dossier_deja_rejete_ne_peut_pas_etre_traite(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');
        $reservation->update(['statut_remboursement' => 'rejete', 'raison_rejet_remboursement' => 'Fraude avérée']);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('rejete', $reservation->fresh()->statut_remboursement);
        $this->assertSame('paye', $reservation->fresh()->statut_paiement);
    }

    public function test_un_dossier_incertain_ne_peut_etre_ni_traite_ni_rejete(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');
        $reservation->update(['statut_remboursement' => 'incertain']);

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect()
            ->assertSessionHasErrors();
        $this->assertSame('incertain', $reservation->fresh()->statut_remboursement);

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/rejeter", ['motif' => 'doublon', 'details' => 'x'])
            ->assertRedirect()
            ->assertSessionHasErrors();
        $this->assertSame('incertain', $reservation->fresh()->statut_remboursement);
    }

    public function test_confirmer_la_verification_ramene_le_dossier_en_attente_et_le_rend_de_nouveau_actionnable(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');
        $reservation->update(['statut_remboursement' => 'incertain']);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/confirmer-verification", [
                'preuve' => 'Vérifié sur le dashboard Stripe : aucun remboursement re_... pour ce PaymentIntent, le transfert n\'a pas eu lieu.',
            ])
            ->assertRedirect()
            ->assertSessionHas('succes');

        $reservation->refresh();
        $this->assertSame('en_attente', $reservation->statut_remboursement);
        $historique = json_decode($reservation->remboursement_historique, true);
        $this->assertSame('verification_confirmee', end($historique)['action']);

        // Et redevient bien actionnable normalement ensuite.
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
                return ['id' => 're_test', 'status' => 'succeeded'];
            }
        };
        $this->app->instance(StripeGatewayInterface::class, $faux);

        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/traiter")
            ->assertRedirect()
            ->assertSessionHas('succes');

        $this->assertSame('traite', $reservation->fresh()->statut_remboursement);
    }

    public function test_confirmer_la_verification_sans_preuve_est_refuse(): void
    {
        $reservation = $this->creerReservationAnnuleeEtPayee('stripe');
        $reservation->update(['statut_remboursement' => 'incertain']);

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post("/remboursements/{$reservation->id_reservation}/confirmer-verification", ['preuve' => ''])
            ->assertRedirect()
            ->assertSessionHasErrors('preuve');

        $this->assertSame('incertain', $reservation->fresh()->statut_remboursement);
    }
}
