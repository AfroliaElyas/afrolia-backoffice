<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Services\MobileMoney\JekoGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vérifie initiate() et verifierStatut() contre les formes de
 * requête/réponse exactes documentées par Jèko ("Paiement en ligne") —
 * aucun appel réseau réel, Http::fake() simule les réponses de l'API.
 */
class JekoGatewayInitiateTest extends TestCase
{
    private function gateway(): JekoGateway
    {
        return new JekoGateway(
            apiKey: 'cle_api_test',
            apiKeyId: 'cle_api_id_test',
            storeId: 'store_test',
            webhookSecret: 'peu importe ici',
            baseUrl: 'https://api.jeko.africa',
        );
    }

    public function test_initiate_envoie_la_requete_documentee_et_renvoie_la_redirectUrl(): void
    {
        Http::fake([
            'https://api.jeko.africa/partner_api/payment_requests' => Http::response([
                'id' => 'preq_123',
                'storeId' => 'store_test',
                'reference' => 'peu-importe-ici',
                'type' => 'redirect',
                'paymentMethod' => 'wave',
                'status' => 'pending',
                'redirectUrl' => 'https://pay.jeko.africa/preq_123',
                'errorReason' => null,
            ], 200),
        ]);

        $paiement = new Paiements(['amount' => 5000]);

        $resultat = $this->gateway()->initiate($paiement, 'wave', '+2250700000000');

        $this->assertSame('pending', $resultat['status']);
        $this->assertSame('https://pay.jeko.africa/preq_123', $resultat['redirect_url']);
        $this->assertSame('preq_123', $resultat['jeko_payment_request_id']);
        $this->assertStringStartsWith('AFR-', $resultat['reference']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.jeko.africa/partner_api/payment_requests'
                && $request->hasHeader('X-API-KEY', 'cle_api_test')
                && $request->hasHeader('X-API-KEY-ID', 'cle_api_id_test')
                && $request['storeId'] === 'store_test'
                && $request['amountCents'] === 5000
                && $request['currency'] === 'XOF'
                && $request['paymentDetails']['type'] === 'redirect'
                && $request['paymentDetails']['data']['paymentMethod'] === 'wave'
                && !empty($request['paymentDetails']['data']['successUrl'])
                && !empty($request['paymentDetails']['data']['errorUrl']);
        });
    }

    public function test_initiate_leve_une_exception_si_jeko_refuse_la_requete(): void
    {
        Http::fake([
            'https://api.jeko.africa/partner_api/payment_requests' => Http::response([
                'error' => 'pay_with_jeko_not_enabled',
            ], 403),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->gateway()->initiate(new Paiements(['amount' => 5000]), 'jeko', '+2250700000000');
    }

    public function test_verifierStatut_lit_le_statut_et_la_transaction(): void
    {
        Http::fake([
            'https://api.jeko.africa/partner_api/payment_requests/preq_123' => Http::response([
                'id' => 'preq_123',
                'status' => 'error',
                'errorReason' => 'insufficient_funds',
                'transaction' => [
                    'id' => 'txn_1',
                    'status' => 'error',
                ],
            ], 200),
        ]);

        $resultat = $this->gateway()->verifierStatut('preq_123');

        $this->assertSame('error', $resultat['statut']);
        $this->assertSame('insufficient_funds', $resultat['raison_erreur']);
        $this->assertSame('txn_1', $resultat['transaction']['id']);
    }
}
