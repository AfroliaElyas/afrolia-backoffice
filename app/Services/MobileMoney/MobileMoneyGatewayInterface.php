<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

interface MobileMoneyGatewayInterface
{
    /**
     * Démarre une collecte auprès de l'opérateur et renvoie une référence
     * de transaction à stocker sur le paiement (provider_transaction_id).
     *
     * redirect_url et jeko_payment_request_id sont spécifiques aux
     * connecteurs qui, comme Jèko, fonctionnent par redirection : le
     * premier est l'URL vers laquelle rediriger la cliente/le client pour
     * finaliser le paiement, le second sert à interroger le statut plus
     * tard (voir JekoGateway::verifierStatut). Absents/null pour un
     * connecteur qui n'en a pas besoin.
     *
     * @return array{reference: string, status: string, redirect_url?: ?string, jeko_payment_request_id?: ?string}
     */
    public function initiate(Paiements $paiement, string $operateur, string $telephone): array;

    /**
     * Vérifie l'authenticité d'une notification webhook reçue de l'agrégateur.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
