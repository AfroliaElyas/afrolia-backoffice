<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

/**
 * Connecteur Jèko (agrégateur Mobile Money retenu pour Afrolia).
 *
 * Vérification de signature confirmée par la documentation "Introduction
 * aux Webhooks" de Jèko et implémentée ci-dessous : chaque livraison est
 * signée par un HMAC-SHA256 du corps brut (non décodé), encodé en
 * hexadécimal, placé dans l'en-tête Jeko-Signature.
 *
 * initiate() n'est volontairement PAS implémentable pour l'instant : la
 * documentation de création d'un paiement (Jèko Checkout) et celle du
 * corps exact de chaque événement webhook (TRANSACTION_COMPLETED,
 * ESCROW_HELD, etc. — page "Événements") n'ont pas encore été fournies.
 * Ne jamais deviner ces formats. Cette classe n'est donc pas branchée
 * comme connecteur actif (voir AppServiceProvider, qui garde
 * GenericMobileMoneyGateway) tant qu'elle n'est pas complète.
 */
class JekoGateway implements MobileMoneyGatewayInterface
{
    public const HEADER_SIGNATURE = 'Jeko-Signature';
    public const HEADER_EVENT = 'Jeko-Event';
    public const HEADER_WEBHOOK_VERSION = 'Jeko-Webhook-Version';

    public function __construct(private readonly ?string $webhookSecret)
    {
    }

    /**
     * @throws \RuntimeException tant que la documentation Jèko Checkout
     *         (endpoint, requête, réponse) n'a pas été fournie.
     */
    public function initiate(Paiements $paiement, string $operateur, string $telephone): array
    {
        throw new \RuntimeException(
            "JekoGateway::initiate() n'est pas encore implémentable : la documentation de création de paiement (Jèko Checkout) n'a pas encore été fournie."
        );
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (empty($this->webhookSecret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
