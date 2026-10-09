<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;

/**
 * Connecteur Jèko (agrégateur Mobile Money retenu pour Afrolia).
 *
 * Vérification de signature et lecture des webhooks confirmées par la
 * documentation Jèko ("Introduction aux Webhooks", "Intégration des
 * Webhooks" et "Événements") et implémentées ci-dessous : chaque livraison
 * est signée par un HMAC-SHA256 du corps brut (non décodé), encodé en
 * hexadécimal, placé dans l'en-tête Jeko-Signature ; l'en-tête Jeko-Event
 * dit quel type d'événement est reçu. Le secret de webhook (différent des
 * clés API) est copié depuis le Dashboard Business, Paramètres > API &
 * Webhooks, une fois l'URL (HTTPS obligatoire) enregistrée là-bas — une
 * seule URL par magasin, une pour l'entreprise.
 *
 * initiate() n'est volontairement PAS implémentable pour l'instant : la
 * documentation de création d'un paiement (Jèko Checkout) n'a pas encore
 * été fournie, et notamment le champ exact qui transporte notre propre
 * référence de paiement (candidat probable d'après "Événements" :
 * transactionDetails.reference, à confirmer). Ne jamais deviner ce format.
 * Cette classe n'est donc pas branchée comme connecteur actif (voir
 * AppServiceProvider, qui garde GenericMobileMoneyGateway) tant qu'elle
 * n'est pas complète.
 */
class JekoGateway implements MobileMoneyGatewayInterface
{
    public const HEADER_SIGNATURE = 'Jeko-Signature';
    public const HEADER_EVENT = 'Jeko-Event';
    public const HEADER_WEBHOOK_VERSION = 'Jeko-Webhook-Version';

    // Valeurs possibles de l'en-tête Jeko-Event (doc "Événements").
    public const EVENT_TRANSACTION_COMPLETED = 'TRANSACTION_COMPLETED';
    public const EVENT_SERVICE_PROVIDER_LINK_REQUEST = 'SERVICE_PROVIDER_LINK_REQUEST';
    public const EVENT_ESCROW_HELD = 'ESCROW_HELD';
    public const EVENT_ESCROW_RELEASED = 'ESCROW_RELEASED';
    public const EVENT_ESCROW_REFUNDED = 'ESCROW_REFUNDED';
    public const EVENT_COMPLIANCE_VERIFICATION_COMPLETED = 'COMPLIANCE_VERIFICATION_COMPLETED';

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

    /**
     * Normalise le corps d'un webhook TRANSACTION_COMPLETED (seul événement
     * pour l'instant exploité : Afrolia encaisse directement sur son propre
     * magasin, sans escrow ni rattachement de Service Provider — à revoir
     * si ce modèle change). Ce corps est la transaction elle-même, sans
     * champ "event" (contrairement aux événements d'escrow).
     *
     * D'après "Intégration des Webhooks" : TRANSACTION_COMPLETED part pour
     * un encaissement réussi (sauf escrow → ESCROW_HELD à la place), un
     * reversement réussi, ET un reversement échoué (statut "error" dans ce
     * cas). Un encaissement qui échoue n'envoie en revanche AUCUN webhook —
     * sa détection demandera d'interroger la demande de paiement
     * directement (voir la page "Gérer les échecs", pas encore fournie),
     * pas seulement d'attendre une notification.
     *
     * `reference` vient de transactionDetails.reference (toujours décrit
     * comme optionnel) : candidat le plus probable pour retrouver notre
     * propre Paiements, mais non confirmé tant que la doc Jèko Checkout
     * (qui dit ce que nous envoyons à la création) n'est pas fournie — ne
     * pas l'utiliser pour faire correspondre un paiement sans cette
     * confirmation.
     *
     * @return array{
     *   id_transaction_jeko: string,
     *   reference: ?string,
     *   statut: string,
     *   montant: int,
     *   devise: string,
     *   frais: int,
     *   methode_paiement: string,
     *   type: string,
     *   store_id: ?string,
     * }
     */
    public function interpreterTransactionCompletee(array $corps): array
    {
        return [
            'id_transaction_jeko' => (string) ($corps['id'] ?? ''),
            'reference' => $corps['transactionDetails']['reference'] ?? null,
            // pending | success | error
            'statut' => (string) ($corps['status'] ?? ''),
            // Unité (FCFA ou centimes) à confirmer avec la doc Jèko Checkout.
            'montant' => (int) ($corps['amount']['amount'] ?? 0),
            'devise' => (string) ($corps['amount']['currency'] ?? ''),
            'frais' => (int) ($corps['fees']['amount'] ?? 0),
            'methode_paiement' => (string) ($corps['paymentMethod'] ?? ''),
            // payment | transfer
            'type' => (string) ($corps['transactionType'] ?? ''),
            'store_id' => $corps['storeId'] ?? null,
        ];
    }
}
