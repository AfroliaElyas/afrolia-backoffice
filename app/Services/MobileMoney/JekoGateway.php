<?php

namespace App\Services\MobileMoney;

use App\Models\Paiements;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Connecteur Jèko (agrégateur Mobile Money retenu pour Afrolia).
 *
 * Toutes les pages nécessaires ont été fournies : "Introduction aux
 * Webhooks", "Intégration des Webhooks", "Événements" et "Paiement en ligne
 * (Jèko Checkout)".
 *
 * Webhooks : chaque livraison est signée par un HMAC-SHA256 du corps brut
 * (non décodé), encodé en hexadécimal, placé dans l'en-tête Jeko-Signature ;
 * l'en-tête Jeko-Event dit quel type d'événement est reçu. Le secret de
 * webhook (différent des clés API) est copié depuis le Dashboard Business,
 * Paramètres > API & Webhooks, une fois l'URL (HTTPS obligatoire) enregistrée
 * là-bas — une seule URL par magasin, une pour l'entreprise.
 *
 * Paiement : POST {base_url}/partner_api/payment_requests en mode "redirect"
 * crée une demande de paiement et renvoie une redirectUrl vers laquelle
 * rediriger la cliente/le client ; successUrl/errorUrl acceptent un lien
 * profond (deep link) de l'application mobile, donc cet appel ne nécessite
 * pas d'hébergement web public — seule la réception du webhook a besoin
 * d'une URL HTTPS publique. GET .../payment_requests/{id} permet de vérifier
 * le statut d'une demande — seul moyen de détecter un paiement (pas un
 * reversement) qui a échoué, puisqu'aucun webhook n'est envoyé dans ce cas.
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

    // paymentMethod acceptés par Jèko (doc "Paiement en ligne").
    public const OPERATEURS_VALIDES = ['wave', 'orange', 'mtn', 'moov', 'djamo', 'jeko', 'bank'];

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $apiKeyId,
        private readonly ?string $storeId,
        private readonly ?string $webhookSecret,
        private readonly string $baseUrl = 'https://api.jeko.africa',
        private readonly string $successUrl = 'afrolia://paiement/succes',
        private readonly string $errorUrl = 'afrolia://paiement/echec',
    ) {
    }

    /**
     * Crée une demande de paiement "redirect" chez Jèko.
     *
     * IMPORTANT — unité de amountCents non confirmée par la documentation :
     * on envoie ici le montant FCFA tel quel, PAS multiplié par 100 (comme
     * pour Stripe ailleurs dans ce projet, le XOF étant une devise sans
     * sous-unité). Les deux interprétations respectent la contrainte
     * documentée ("multiple de 100, minimum 100"), donc ceci DOIT être
     * vérifié avec un vrai paiement de test avant la mise en production —
     * ne jamais supposer que c'est correct sans ce test.
     *
     * @return array{reference: string, status: string, redirect_url: ?string, jeko_payment_request_id: ?string}
     */
    public function initiate(Paiements $paiement, string $operateur, string $telephone): array
    {
        if (empty($this->apiKey) || empty($this->apiKeyId) || empty($this->storeId)) {
            throw new \RuntimeException(
                "JekoGateway n'est pas configuré (clé API, clé API ID ou identifiant de magasin manquant)."
            );
        }

        $methode = strtolower($operateur);
        if (!in_array($methode, self::OPERATEURS_VALIDES, true)) {
            throw new \InvalidArgumentException("Moyen de paiement Jèko inconnu : {$operateur}");
        }

        $reference = 'AFR-' . strtoupper(Str::random(12));

        $reponse = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'X-API-KEY-ID' => $this->apiKeyId,
        ])->post("{$this->baseUrl}/partner_api/payment_requests", [
            'storeId' => $this->storeId,
            'amountCents' => (int) round($paiement->amount),
            'currency' => 'XOF',
            'reference' => $reference,
            'paymentDetails' => [
                'type' => 'redirect',
                'data' => [
                    'paymentMethod' => $methode,
                    'successUrl' => $this->successUrl,
                    'errorUrl' => $this->errorUrl,
                ],
            ],
        ]);

        if ($reponse->failed()) {
            throw new \RuntimeException(
                'Échec de la création du paiement Jèko : ' . $reponse->status() . ' ' . $reponse->body()
            );
        }

        $corps = $reponse->json();

        return [
            'reference' => $reference,
            'status' => (string) ($corps['status'] ?? 'pending'),
            'redirect_url' => $corps['redirectUrl'] ?? null,
            'jeko_payment_request_id' => $corps['id'] ?? null,
        ];
    }

    /**
     * Interroge l'état d'une demande de paiement Jèko. Seul moyen de
     * détecter un paiement (pas un reversement) qui a échoué, puisqu'aucun
     * webhook n'est envoyé dans ce cas précis — à utiliser en secours si le
     * webhook n'arrive pas (doc Jèko : "webhooks comme source de vérité,
     * interrogation du statut comme solution de repli").
     *
     * @return array{statut: string, transaction: ?array, raison_erreur: ?string}
     */
    public function verifierStatut(string $paymentRequestId): array
    {
        $reponse = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'X-API-KEY-ID' => $this->apiKeyId,
        ])->get("{$this->baseUrl}/partner_api/payment_requests/{$paymentRequestId}");

        if ($reponse->failed()) {
            throw new \RuntimeException(
                'Échec de la vérification du statut Jèko : ' . $reponse->status() . ' ' . $reponse->body()
            );
        }

        $corps = $reponse->json();

        return [
            'statut' => (string) ($corps['status'] ?? ''),
            'transaction' => $corps['transaction'] ?? null,
            'raison_erreur' => $corps['errorReason'] ?? null,
        ];
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
     * sa détection passe par verifierStatut() ci-dessus, pas seulement par
     * l'attente d'une notification.
     *
     * `reference` vient de transactionDetails.reference : confirmé par la
     * doc "Paiement en ligne" comme étant exactement la référence que nous
     * envoyons nous-mêmes à la création (voir initiate() ci-dessus).
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
