<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adaptateur Jèko Africa (agrégateur Mobile Money).
 *
 * Flux « redirect » : on crée une demande de paiement chez Jèko, qui renvoie
 * une URL de paiement que l'app ouvre. La confirmation finale n'arrive que
 * par webhook signé (en-tête Jeko-Signature) : c'est la seule source de
 * vérité, le retour du client sur la page de succès ne prouve rien.
 *
 * Doc : https://developer.jeko.africa/docs/payments/checkout
 */
class JekoMobileMoneyGateway implements MobileMoneyGatewayInterface
{
    /** Moyens de paiement acceptés par Jèko pour l'encaissement. */
    public const OPERATEURS = ['wave', 'orange', 'mtn', 'moov', 'djamo'];

    public function initiate(Paiements $paiement, string $operateur, ?string $telephone): array
    {
        $cle = config('services.jeko.api_key');
        $cleId = config('services.jeko.api_key_id');
        $storeId = config('services.jeko.store_id');

        if (empty($cle) || empty($cleId) || empty($storeId)) {
            throw new RuntimeException('Jèko non configuré (JEKO_API_KEY, JEKO_API_KEY_ID, JEKO_STORE_ID).');
        }

        // Référence unique côté Afrolia (5 à 100 caractères). Elle commence
        // par l'id du paiement pour pouvoir le retrouver depuis le webhook.
        $reference = 'AFR-' . $paiement->id_paiement . '-' . strtoupper(Str::random(6));

        $retour = rtrim((string) config('services.jeko.return_url_base'), '/');

        $reponse = Http::withHeaders([
                'X-API-KEY' => $cle,
                'X-API-KEY-ID' => $cleId,
            ])
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim(config('services.jeko.base_url'), '/') . '/payment_requests', [
                'storeId' => $storeId,
                // Jèko attend le montant en centimes (minimum 100, multiple
                // de 100). Le XOF n'a pas de décimales : 1 000 F => 100000.
                'amountCents' => (int) round($paiement->amount) * 100,
                'currency' => 'XOF',
                'reference' => $reference,
                'paymentDetails' => [
                    'type' => 'redirect',
                    'data' => [
                        'paymentMethod' => $operateur,
                        'successUrl' => $retour . '/paiement/retour?statut=success&reference=' . $reference,
                        'errorUrl' => $retour . '/paiement/retour?statut=error&reference=' . $reference,
                    ],
                ],
            ]);

        if (!$reponse->successful() || empty($reponse->json('id')) || empty($reponse->json('redirectUrl'))) {
            throw new RuntimeException('Jèko a refusé la demande de paiement (HTTP ' . $reponse->status() . ').');
        }

        return [
            // On stocke l'id Jèko : c'est lui qui revient dans le webhook.
            'reference' => (string) $reponse->json('id'),
            'status' => 'pending',
            'redirect_url' => (string) $reponse->json('redirectUrl'),
        ];
    }

    /**
     * Jeko-Signature = HMAC-SHA256 du corps BRUT, en hexadécimal minuscule,
     * avec le secret webhook du Dashboard Jèko (Paramètres → API & Webhooks).
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.jeko.webhook_secret');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower(trim($signature)));
    }
}
