<?php

namespace App\Services\Stripe;

interface StripeGatewayInterface
{
    /**
     * Crée un PaymentIntent pour le montant donné (en FCFA, devise
     * zéro-décimale : le montant est transmis tel quel, sans le
     * multiplier par 100).
     *
     * @return array{id: string, client_secret: string, status: string}
     */
    public function createPaymentIntent(float $montant): array;

    /**
     * Récupère l'état actuel d'un PaymentIntent existant.
     *
     * @return array{id: string, client_secret: string, status: string}
     */
    public function retrievePaymentIntent(string $paymentIntentId): array;

    /**
     * Rembourse (totalement ou partiellement) un paiement déjà réussi.
     *
     * @return array{id: string, status: string}
     */
    public function refund(string $paymentIntentId, float $montant): array;
}
