<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

interface MobileMoneyGatewayInterface
{
    /**
     * Démarre une collecte auprès de l'opérateur et renvoie une référence
     * de transaction à stocker sur le paiement (provider_transaction_id).
     *
     * @return array{reference: string, status: string}
     */
    public function initiate(Paiements $paiement, string $operateur, string $telephone): array;

    /**
     * Vérifie l'authenticité d'une notification webhook reçue de l'agrégateur.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
