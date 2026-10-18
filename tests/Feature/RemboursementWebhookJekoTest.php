<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\MobileMoney\JekoGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le webhook Jèko est la seule façon de confirmer après coup l'issue d'un
 * transfert de remboursement dont la réponse synchrone se serait perdue
 * (demandé le 18/10/2026) : ces tests vérifient qu'il retrouve bien le
 * dossier par sa référence stable, de façon idempotente, sans jamais
 * écraser une conclusion déjà établie par ailleurs.
 */
class RemboursementWebhookJekoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret_webhook_test';

    private function bindJekoGateway(): void
    {
        $this->app->instance(MobileMoneyGatewayInterface::class, new JekoGateway(
            apiKey: 'x',
            apiKeyId: 'x',
            storeId: 'x',
            webhookSecret: self::SECRET,
        ));
    }

    private function envoyerWebhook(array $corps): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($corps);
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        return $this->call(
            'POST',
            '/api/mobile-money/webhook',
            [],
            [],
            [],
            [
                'HTTP_Jeko-Event' => 'TRANSACTION_COMPLETED',
                'HTTP_Jeko-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }

    private function creerReservationEnCoursDeRemboursement(): Reservations
    {
        $coiffeuse = UsersApp::create(['name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000080', 'password' => 'x', 'role' => 'hair']);
        $client = UsersApp::create(['name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000081', 'password' => 'x', 'role' => 'user']);
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
            'statut_remboursement' => 'en_cours',
            'remboursement_reference' => 'REMB-PAIEMENT-999',
            'annule_par' => 'client',
            'prix_service' => 10000,
            'montant_commission' => 1234,
            'montant_total' => 10000,
            'methode_paiement' => 'mobile_money',
        ]);

        Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'amount' => 10000,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'mobile_money_operateur' => 'wave',
            'mobile_money_telephone' => '+2250701234567',
            'status' => 'succeeded',
        ]);

        return $reservation;
    }

    public function test_un_webhook_de_succes_confirme_le_remboursement(): void
    {
        $this->bindJekoGateway();
        $reservation = $this->creerReservationEnCoursDeRemboursement();

        $this->envoyerWebhook([
            'id' => 'jeko_tx_1',
            'status' => 'success',
            'transactionDetails' => ['reference' => 'REMB-PAIEMENT-999'],
            'amount' => ['amount' => 8766, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);

        $reservation->refresh();
        $this->assertSame('traite', $reservation->statut_remboursement);
        $this->assertSame('rembourse', $reservation->statut_paiement);
        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $reservation->id_reservation,
            'status' => 'refunded',
        ]);
    }

    public function test_un_webhook_de_succes_redelivre_deux_fois_ne_change_rien_la_deuxieme_fois(): void
    {
        $this->bindJekoGateway();
        $reservation = $this->creerReservationEnCoursDeRemboursement();

        $corps = [
            'id' => 'jeko_tx_1',
            'status' => 'success',
            'transactionDetails' => ['reference' => 'REMB-PAIEMENT-999'],
            'amount' => ['amount' => 8766, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ];

        $this->envoyerWebhook($corps)->assertStatus(200);
        $this->envoyerWebhook($corps)->assertStatus(200);

        $reservation->refresh();
        $this->assertSame('traite', $reservation->statut_remboursement);
        $historique = json_decode($reservation->remboursement_historique, true);
        // Une seule entrée "rembourse", pas deux : la redelivrance est un no-op.
        $this->assertCount(1, array_filter($historique, fn ($e) => $e['action'] === 'rembourse'));
    }

    public function test_un_webhook_dechec_remet_le_dossier_en_attente(): void
    {
        $this->bindJekoGateway();
        $reservation = $this->creerReservationEnCoursDeRemboursement();

        $this->envoyerWebhook([
            'id' => 'jeko_tx_1',
            'status' => 'error',
            'transactionDetails' => ['reference' => 'REMB-PAIEMENT-999'],
            'amount' => ['amount' => 8766, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);

        $reservation->refresh();
        $this->assertSame('en_attente', $reservation->statut_remboursement);
        $this->assertSame('paye', $reservation->statut_paiement);
    }

    public function test_un_webhook_dechec_arrivant_apres_un_succes_deja_confirme_ne_defait_rien(): void
    {
        $this->bindJekoGateway();
        $reservation = $this->creerReservationEnCoursDeRemboursement();
        // Le traitement synchrone a déjà conclu au succès avant que ce
        // webhook (en retard, ou un doublon mal formé) n'arrive.
        $reservation->update(['statut_remboursement' => 'traite', 'statut_paiement' => 'rembourse']);

        $this->envoyerWebhook([
            'id' => 'jeko_tx_1',
            'status' => 'error',
            'transactionDetails' => ['reference' => 'REMB-PAIEMENT-999'],
            'amount' => ['amount' => 8766, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);

        $reservation->refresh();
        $this->assertSame('traite', $reservation->statut_remboursement);
        $this->assertSame('rembourse', $reservation->statut_paiement);
    }

    public function test_une_reference_inconnue_est_ignoree_sans_erreur(): void
    {
        $this->bindJekoGateway();

        // Correspond par exemple à un versement de gains (VERS-...), pas à
        // un remboursement : ne doit provoquer aucune erreur.
        $this->envoyerWebhook([
            'id' => 'jeko_tx_1',
            'status' => 'success',
            'transactionDetails' => ['reference' => 'VERS-20261018-ABCDEFGH'],
            'amount' => ['amount' => 5000, 'currency' => 'XOF'],
            'fees' => ['amount' => 0],
            'paymentMethod' => 'wave',
            'transactionType' => 'transfer',
            'storeId' => 'x',
        ])->assertStatus(200);
    }
}
