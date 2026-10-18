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
     * $idempotencyKey (stable, dérivée de l'identifiant du paiement) :
     * si un appel précédent a réellement atteint Stripe mais que la
     * réponse s'est perdue (coupure réseau, timeout), Stripe renvoie le
     * même remboursement déjà créé au lieu d'en créer un second lors
     * d'une nouvelle tentative avec la même clé (demandé le 18/10/2026,
     * voir https://docs.stripe.com/api/idempotent_requests).
     *
     * @return array{id: string, status: string}
     */
    public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array;
}
