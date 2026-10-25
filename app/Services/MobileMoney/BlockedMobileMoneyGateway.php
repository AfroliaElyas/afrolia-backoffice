<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

/**
 * Pendant de BlockedStripeGateway pour Mobile Money (Jèko) : voir ce
 * fichier pour l'explication complète. Lié uniquement quand
 * app()->environment('testing').
 */
class BlockedMobileMoneyGateway implements MobileMoneyGatewayInterface
{
    private function refuser(): never
    {
        throw new \RuntimeException(
            'MobileMoneyGatewayInterface a été appelé pendant un test sans avoir été remplacé par un faux '
            . '($this->app->instance(MobileMoneyGatewayInterface::class, ...)). Aucun appel réel à Jèko '
            . "n'est autorisé pendant les tests, même en environnement de production."
        );
    }

    public function initiate(Paiements $paiement, string $operateur, string $telephone): array
    {
        $this->refuser();
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $this->refuser();
    }

    public function refund(array $destinataire, float $montant, string $reference): array
    {
        $this->refuser();
    }

    public function verser(array $destinataire, float $montant, string $reference): array
    {
        $this->refuser();
    }
}
