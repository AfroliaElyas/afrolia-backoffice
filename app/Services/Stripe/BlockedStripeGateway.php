<?php

namespace App\Services\Stripe;

/**
 * Lié à StripeGatewayInterface uniquement quand app()->environment('testing')
 * (voir AppServiceProvider), à la place de RealStripeGateway. Un test qui a
 * besoin de Stripe doit explicitement remplacer ce faux par le sien
 * ($this->app->instance(StripeGatewayInterface::class, ...)), comme le font
 * déjà tous les tests existants qui touchent à Stripe. Si un test oublie de
 * le faire et qu'un code appelle malgré tout cette interface, l'échec est
 * immédiat et explicite au lieu d'atteindre silencieusement le vrai Stripe
 * de production si jamais exécuté avec la vraie clé secrète (déploiement
 * Forge, voir deploy/forge-deploy.sh).
 */
class BlockedStripeGateway implements StripeGatewayInterface
{
    private function refuser(): never
    {
        throw new \RuntimeException(
            'StripeGatewayInterface a été appelé pendant un test sans avoir été remplacé par un faux '
            . '($this->app->instance(StripeGatewayInterface::class, ...)). Aucun appel réel à Stripe '
            . "n'est autorisé pendant les tests, même en environnement de production."
        );
    }

    public function createPaymentIntent(float $montant): array
    {
        $this->refuser();
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        $this->refuser();
    }

    public function refund(string $paymentIntentId, float $montant, ?string $idempotencyKey = null): array
    {
        $this->refuser();
    }
}
