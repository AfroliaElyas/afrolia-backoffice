<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

interface MobileMoneyGatewayInterface
{
    /**
     * Démarre une collecte auprès de l'opérateur et renvoie une référence
     * de transaction à stocker sur le paiement (provider_transaction_id).
     *
     * `redirect_url` n'est renseigné que par les agrégateurs qui font payer
     * sur leur propre page (ex. Jèko) : l'app doit alors l'ouvrir.
     *
     * @return array{reference: string, status: string, redirect_url?: string}
     */
    public function initiate(Paiements $paiement, string $operateur, ?string $telephone): array;

    /**
     * Vérifie l'authenticité d'une notification webhook reçue de l'agrégateur.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
