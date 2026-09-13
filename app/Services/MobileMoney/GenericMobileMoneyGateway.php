<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;
use Illuminate\Support\Str;

/**
 * Adaptateur en attente du choix définitif de l'agrégateur Mobile Money
 * (CinetPay, PayDunya, Fedapay...). Il génère une référence locale et
 * attend une notification webhook signée pour confirmer le paiement.
 * À remplacer par un adaptateur appelant réellement l'API du PSP retenu.
 */
class GenericMobileMoneyGateway implements MobileMoneyGatewayInterface
{
    public function initiate(Paiements $paiement, string $operateur, string $telephone): array
    {
        $reference = 'MM-' . strtoupper(Str::random(12));

        return [
            'reference' => $reference,
            'status' => 'pending',
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.mobile_money.webhook_secret');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
