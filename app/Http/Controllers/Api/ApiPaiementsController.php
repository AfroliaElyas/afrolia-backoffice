<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commandes;
use App\Models\Gains;
use App\Models\Paiements;
use App\Models\Produits;
use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\AbonnementService;
use App\Services\MobileMoney\JekoGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class ApiPaiementsController extends Controller
{
    public function __construct(
        private readonly MobileMoneyGatewayInterface $mobileMoneyGateway,
        private readonly AbonnementService $abonnements,
        private readonly StripeGatewayInterface $stripeGateway
    ) {
    }

    // ✅ 1. Liste des paiements d'un utilisateur
    public function getPaiementByUser(Request $request, $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json(['message' => 'Vous ne pouvez consulter que vos propres paiements'], 403);
        }

        // La table paiements n'a pas de colonne id_utilisateur : un paiement
        // est toujours rattaché à une réservation OU une commande, chacune
        // ayant son propre id_client.
        $paiements = Paiements::where(function ($query) use ($id_utilisateur) {
                $query->whereHas('reservation', fn ($q) => $q->where('id_client', $id_utilisateur))
                    ->orWhereHas('commande', fn ($q) => $q->where('id_client', $id_utilisateur));
            })
            ->with(['reservation', 'commande'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paiements,
        ]);
    }

    // ✅ 1bis. Statut du paiement d'une réservation (pour le polling client)
    public function getPaiementStatusByReservation(Request $request, $id_reservation)
    {
        $reservation = Reservations::find($id_reservation);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Réservation non trouvée'], 404);
        }

        $userId = (int) $request->user()->id_user_app;
        if ((int) $reservation->id_client !== $userId && (int) $reservation->id_coiffeur !== $userId) {
            return response()->json(['success' => false, 'message' => 'Réservation non trouvée'], 404);
        }

        $paiement = Paiements::where('id_reservation', $id_reservation)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'statut_paiement' => $reservation->statut_paiement,
                'statut_reservation' => $reservation->statut,
                'paiement' => $paiement,
            ],
        ]);
    }

    // ✅ 1ter. Statut du paiement d'une commande boutique (pour le polling client)
    public function getPaiementStatusByCommande(Request $request, $id_commande)
    {
        $commande = Commandes::find($id_commande);

        if (!$commande) {
            return response()->json(['success' => false, 'message' => 'Commande non trouvée'], 404);
        }

        $userId = (int) $request->user()->id_user_app;
        if ((int) $commande->id_client !== $userId && (int) $commande->id_coiffeur !== $userId) {
            return response()->json(['success' => false, 'message' => 'Commande non trouvée'], 404);
        }

        $paiement = Paiements::where('id_commande', $id_commande)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'statut_paiement' => $commande->statut_paiement,
                'statut_commande' => $commande->statut_commande,
                'paiement' => $paiement,
            ],
        ]);
    }

    // ✅ 2. Création d’un paiement (pour une réservation OU une commande boutique)
    public function store(Request $request)
    {
        $rules = [
            'montant' => 'required|numeric',
            'id_reservation' => 'required_without:id_commande|prohibits:id_commande|integer',
            'id_commande' => 'required_without:id_reservation|prohibits:id_reservation|integer',
            'methode' => 'sometimes|string|in:stripe,mobile_money',
            'operateur' => 'required_if:methode,mobile_money|string|in:orange,mtn,moov',
            'telephone' => 'required_if:methode,mobile_money|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all())
            ], 422);
        }

        if ($request->filled('id_reservation')) {
            $payable = Reservations::find($request->id_reservation);
            $payableLabel = 'Réservation';
        } else {
            $payable = Commandes::find($request->id_commande);
            $payableLabel = 'Commande';
        }

        if (!$payable) {
            return response()->json(['success' => false, 'message' => "$payableLabel non trouvée"], 404);
        }

        if ((int) $payable->id_client !== (int) $request->user()->id_user_app) {
            return response()->json(['success' => false, 'message' => "$payableLabel non trouvée"], 404);
        }

        if ($payable->statut_paiement === 'paye') {
            return response()->json(['success' => false, 'message' => 'Ce paiement a déjà été effectué'], 409);
        }
        $statut = $payable instanceof Reservations ? $payable->statut : $payable->statut_commande;
        if (in_array($statut, ['annulee', 'terminee', 'no_show', 'livree'], true)) {
            return response()->json(['success' => false, 'message' => 'Cette réservation ou commande ne peut plus être payée'], 409);
        }

        $paiementBase = $request->filled('id_reservation')
            ? ['id_reservation' => $payable->id_reservation]
            : ['id_commande' => $payable->id_commande];

        // Le montant réellement facturé est toujours celui déjà calculé et
        // stocké côté serveur sur la réservation/commande (lui-même basé sur
        // la formule d'abonnement de la coiffeuse) : on ignore délibérément
        // le montant envoyé par le client pour éviter tout écart.
        $montantAutorise = (float) $payable->montant_total;

        $methode = $request->input('methode', 'stripe');

        if ($methode === 'mobile_money') {
            return $this->storeMobileMoneyPaiement($request, $paiementBase, $montantAutorise);
        }

        return $this->storeStripePaiement($request, $paiementBase, $montantAutorise);
    }

    private function storeStripePaiement(Request $request, array $paiementBase, float $montantAutorise)
    {
        // Une nouvelle tentative (timeout réseau, écran relancé...) sur la
        // même réservation/commande ne doit jamais créer un second
        // PaymentIntent : si une tentative Stripe est encore en cours, on la
        // réutilise plutôt que d'exposer un deuxième moyen de débiter le
        // client pour le même achat.
        $existant = Paiements::where($paiementBase)
            ->where('payment_method', 'stripe')
            ->where('status', 'pending')
            ->latest('id_paiement')
            ->first();

        if ($existant && $existant->payment_intent_id) {
            $intentExistant = $this->stripeGateway->retrievePaymentIntent($existant->payment_intent_id);

            if (!in_array($intentExistant['status'], ['succeeded', 'canceled'], true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paiement déjà initié, en attente de confirmation',
                    'client_secret' => $intentExistant['client_secret'],
                    'paiement' => $existant,
                ]);
            }
        }

        $intent = $this->stripeGateway->createPaymentIntent($montantAutorise);

        $paiement = Paiements::create($paiementBase + [
            'payment_intent_id' => $intent['id'],
            'amount' => $montantAutorise,
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré avec succès',
            'client_secret' => $intent['client_secret'],
            'paiement' => $paiement
        ]);
    }

    private function storeMobileMoneyPaiement(Request $request, array $paiementBase, float $montantAutorise)
    {
        if ($this->mobileMoneyGateway instanceof \App\Services\MobileMoney\GenericMobileMoneyGateway) {
            return response()->json([
                'success' => false,
                'message' => 'Le paiement Mobile Money est temporairement indisponible.',
            ], 503);
        }

        $paiement = Paiements::create($paiementBase + [
            'amount' => $montantAutorise,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'mobile_money_operateur' => strtolower((string) $request->input('operateur')),
            'mobile_money_telephone' => $request->input('telephone'),
            'status' => 'pending',
        ]);

        try {
            $result = $this->mobileMoneyGateway->initiate(
                $paiement,
                $request->input('operateur'),
                $request->input('telephone')
            );
        } catch (\InvalidArgumentException $e) {
            $paiement->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Log::error('Échec initiate() mobile money', ['error' => $e->getMessage()]);
            $paiement->update(['status' => 'failed', 'failure_reason' => 'Erreur du fournisseur de paiement']);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de contacter le fournisseur de paiement mobile money. Veuillez réessayer.',
            ], 502);
        }

        $paiement->update([
            'provider_transaction_id' => $result['reference'],
            'jeko_payment_request_id' => $result['jeko_payment_request_id'] ?? null,
            'status' => $result['status'] ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement mobile money initié, en attente de confirmation',
            'reference' => $result['reference'],
            // URL de redirection Jèko : à ouvrir côté app pour que
            // la cliente/le client effectue le paiement sur son opérateur.
            'redirect_url' => $result['redirect_url'] ?? null,
            'paiement' => $paiement,
        ]);
    }

    // Pas de update()/destroy() ici par conception : le statut d'un
    // paiement ne doit changer que via le webhook Stripe/mobile money
    // (voir stripeWebhook/mobileMoneyWebhook plus bas), jamais sur simple
    // requête du client qui l'a initié — un client aurait pu faire passer
    // son propre paiement en attente directement à "succeeded".

    public function stripeWebhook(Request $request)
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $webhookSecret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalide', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Signature invalide'], 400);
        }

        $intent = $event->data->object;
        $paiement = Paiements::where('payment_intent_id', $intent->id)->first();

        if (!$paiement) {
            return response()->json(['status' => 'ok']);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->markPaiementSucceeded($paiement),
            'payment_intent.payment_failed' => $this->markPaiementFailed($paiement, 'Paiement refusé par Stripe'),
            'payment_intent.canceled' => $this->markPaiementFailed($paiement, 'Paiement annulé'),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    public function mobileMoneyWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header(JekoGateway::HEADER_SIGNATURE);

        if (!$this->mobileMoneyGateway->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Webhook Mobile Money : signature invalide');

            return response()->json(['error' => 'Signature invalide'], 400);
        }

        // Seul TRANSACTION_COMPLETED est exploité pour l'instant : Afrolia
        // encaisse directement sur son propre magasin, sans escrow ni
        // Service Provider (voir JekoGateway::interpreterTransactionCompletee).
        if ($request->header(JekoGateway::HEADER_EVENT) !== JekoGateway::EVENT_TRANSACTION_COMPLETED) {
            return response()->json(['status' => 'ok']);
        }

        if (!$this->mobileMoneyGateway instanceof JekoGateway) {
            return response()->json(['status' => 'ok']);
        }

        $transaction = $this->mobileMoneyGateway->interpreterTransactionCompletee((array) $request->json()->all());

        $paiement = Paiements::where('provider_transaction_id', $transaction['reference'])->first();

        if (!$paiement) {
            return response()->json(['status' => 'ok']);
        }

        match ($transaction['statut']) {
            'success' => $this->markPaiementSucceeded($paiement),
            'error' => $this->markPaiementFailed($paiement, 'Paiement mobile money (Jèko) : échec'),
            // pending ou valeur inconnue : rien à faire, on attend la suite.
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function markPaiementSucceeded(Paiements $paiement): void
    {
        DB::transaction(function () use ($paiement) {
            $locked = Paiements::whereKey($paiement->getKey())->lockForUpdate()->first();
            if ($locked) $this->applyPaiementSucceeded($locked);
        }, 3);
    }

    private function applyPaiementSucceeded(Paiements $paiement): void
    {
        // Idempotence : un webhook peut être livré plusieurs fois par le fournisseur
        if ($paiement->status === 'succeeded') {
            return;
        }

        $paiement->update([
            'status' => 'succeeded',
            'processed_at' => now(),
        ]);

        if ($paiement->id_commande) {
            $this->marquerCommandePayee($paiement);
            return;
        }

        $reservation = Reservations::whereKey($paiement->id_reservation)->lockForUpdate()->first();

        if (!$reservation || $reservation->statut_paiement === 'paye') {
            return;
        }

        $reservation->update(['statut_paiement' => 'paye']);

        // Utiliser les montants de la réservation : une modification de
        // formule entre réservation et paiement ne doit pas changer le gain.
        $montant_brut = $paiement->amount;
        $montant_net = $reservation->prix_service;
        $commission = $reservation->montant_commission;

        Gains::create([
            'id_coiffeur' => $reservation->id_coiffeur,
            'id_reservation' => $reservation->id_reservation,
            'montant_brut' => $montant_brut,
            'montant_commission' => $commission,
            'montant_net' => $montant_net,
            'statut' => 'en_attente',
        ]);
    }

    private function marquerCommandePayee(Paiements $paiement): void
    {
        $commande = Commandes::whereKey($paiement->id_commande)->lockForUpdate()->first();

        if (!$commande || $commande->statut_paiement === 'paye') {
            return;
        }

        $commande->update(['statut_paiement' => 'paye', 'statut_commande' => 'payee']);

        Gains::create([
            'id_coiffeur' => $commande->id_coiffeur,
            'id_commande' => $commande->id_commande,
            'montant_brut' => $commande->montant_total,
            'montant_commission' => $commande->montant_commission,
            'montant_net' => $commande->montant_produits,
            'statut' => 'en_attente',
        ]);
    }

    private function markPaiementFailed(Paiements $paiement, string $raison): void
    {
        DB::transaction(function () use ($paiement, $raison) {
            $locked = Paiements::whereKey($paiement->getKey())->lockForUpdate()->first();
            if ($locked) $this->applyPaiementFailed($locked, $raison);
        }, 3);
    }

    private function applyPaiementFailed(Paiements $paiement, string $raison): void
    {
        if (in_array($paiement->status, ['succeeded', 'failed', 'cancelled'], true)) {
            return;
        }

        $paiement->update([
            'status' => 'failed',
            'failure_reason' => $raison,
            'processed_at' => now(),
        ]);

        if ($paiement->id_commande) {
            $this->annulerCommandeEtRestituerStock($paiement, $raison);
            return;
        }

        $reservation = Reservations::whereKey($paiement->id_reservation)->lockForUpdate()->first();

        // La réservation reste non payée (elle n'est pas annulée automatiquement,
        // le client peut retenter un paiement).
        if ($reservation && $reservation->statut_paiement !== 'paye') {
            $reservation->update(['statut_paiement' => 'echoue']);
        }
    }

    private function annulerCommandeEtRestituerStock(Paiements $paiement, string $raison): void
    {
        $commande = Commandes::with('lignes')->whereKey($paiement->id_commande)->lockForUpdate()->first();

        if (!$commande || $commande->statut_commande === 'annulee' || $commande->statut_paiement === 'paye') {
            return;
        }

        // Le stock avait été réservé dès la création de la commande : on le
        // restitue puisque le paiement n'a pas abouti.
        foreach ($commande->lignes as $ligne) {
            Produits::where('id_produit', $ligne->id_produit)
                ->increment('quantite_stock', $ligne->quantite);
        }

        $commande->update([
            'statut_paiement' => 'echoue',
            'statut_commande' => 'annulee',
            'raison_annulation' => $raison,
            'annulee_le' => now(),
        ]);
    }
}
