<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gains;
use App\Models\Paiements;
use App\Models\Reservations;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

class ApiPaiementsController extends Controller
{
    public function __construct(
        private readonly MobileMoneyGatewayInterface $mobileMoneyGateway
    ) {
    }

    // ✅ 1. Liste (ou first) des paiements d’un utilisateur
    public function getPaiementByUser($id_utilisateur)
    {
        $paiement = Paiements::where('id_utilisateur', $id_utilisateur)
            ->with(['reservation'])
            ->first();

        if (!$paiement) {
            return response()->json(['message' => 'Aucun paiement trouvé'], 404);
        }

        return response()->json($paiement);
    }

    // ✅ 1bis. Statut du paiement d'une réservation (pour le polling client)
    public function getPaiementStatusByReservation($id_reservation)
    {
        $reservation = Reservations::find($id_reservation);

        if (!$reservation) {
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

    // ✅ 2. Création d’un paiement
    public function store(Request $request)
    {
        $rules = [
            'montant' => 'required|numeric',
            'id_reservation' => 'required|integer',
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

        $reservation = Reservations::find($request->id_reservation);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Réservation non trouvée'], 404);
        }

        $methode = $request->input('methode', 'stripe');

        if ($methode === 'mobile_money') {
            return $this->storeMobileMoneyPaiement($request, $reservation);
        }

        return $this->storeStripePaiement($request, $reservation);
    }

    private function storeStripePaiement(Request $request, Reservations $reservation)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        // 🔵 1 — Créer PaymentIntent
        $intent = PaymentIntent::create([
            'amount' => $request->montant * 100,  // Stripe en centimes
            'currency' => 'xof',
            'payment_method_types' => ['card'],
        ]);

        // 🔵 2 — Enregistrer le paiement (status = pending)
        $paiement = Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'payment_intent_id' => $intent->id,
            'amount' => $request->montant,
            'currency' => 'XOF',
            'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré avec succès',
            'client_secret' => $intent->client_secret,
            'paiement' => $paiement
        ]);
    }

    private function storeMobileMoneyPaiement(Request $request, Reservations $reservation)
    {
        $paiement = Paiements::create([
            'id_reservation' => $reservation->id_reservation,
            'amount' => $request->montant,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'status' => 'pending',
        ]);

        $result = $this->mobileMoneyGateway->initiate(
            $paiement,
            $request->input('operateur'),
            $request->input('telephone')
        );

        $paiement->update([
            'provider_transaction_id' => $result['reference'],
            'status' => $result['status'] ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement mobile money initié, en attente de confirmation',
            'reference' => $result['reference'],
            'paiement' => $paiement,
        ]);
    }

    // ✅ 3. Mise à jour d’un paiement
    public function update(Request $request, $id_paiement)
    {
        $paiement = Paiements::find($id_paiement);

        if (!$paiement) {
            return response()->json(['message' => 'Paiement non trouvé'], 404);
        }

        $paiement->update($request->all());

        return response()->json([
            'message' => 'Paiement mis à jour avec succès',
            'data' => $paiement
        ]);
    }

    // ✅ 4. Suppression d’un paiement
    public function destroy($id_paiement)
    {
        $paiement = Paiements::find($id_paiement);

        if (!$paiement) {
            return response()->json(['message' => 'Paiement non trouvé'], 404);
        }

        $paiement->delete();

        return response()->json(['message' => 'Paiement supprimé avec succès']);
    }

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
        $signature = $request->header('X-MobileMoney-Signature');

        if (!$this->mobileMoneyGateway->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Webhook Mobile Money : signature invalide');

            return response()->json(['error' => 'Signature invalide'], 400);
        }

        $reference = $request->input('reference');
        $statut = $request->input('statut');

        $paiement = Paiements::where('provider_transaction_id', $reference)->first();

        if (!$paiement) {
            return response()->json(['status' => 'ok']);
        }

        match ($statut) {
            'SUCCESS' => $this->markPaiementSucceeded($paiement),
            'FAILED', 'CANCELLED' => $this->markPaiementFailed($paiement, "Paiement mobile money: {$statut}"),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function markPaiementSucceeded(Paiements $paiement): void
    {
        // Idempotence : un webhook peut être livré plusieurs fois par le fournisseur
        if ($paiement->status === 'succeeded') {
            return;
        }

        $paiement->update([
            'status' => 'succeeded',
            'processed_at' => now(),
        ]);

        $reservation = Reservations::find($paiement->id_reservation);

        if (!$reservation) {
            return;
        }

        $reservation->update(['statut_paiement' => 'paye']);

        $montant_brut = $paiement->amount;
        $montant_net = $montant_brut / 1.15;
        $commission = $montant_brut - $montant_net;

        Gains::create([
            'id_coiffeur' => $reservation->id_coiffeur,
            'id_reservation' => $reservation->id_reservation,
            'montant_brut' => $montant_brut,
            'montant_commission' => $commission,
            'montant_net' => $montant_net,
            'statut' => 'en_attente',
        ]);
    }

    private function markPaiementFailed(Paiements $paiement, string $raison): void
    {
        if (in_array($paiement->status, ['succeeded', 'failed', 'cancelled'], true)) {
            return;
        }

        $paiement->update([
            'status' => 'failed',
            'failure_reason' => $raison,
            'processed_at' => now(),
        ]);

        $reservation = Reservations::find($paiement->id_reservation);

        // La réservation reste non payée (elle n'est pas annulée automatiquement,
        // le client peut retenter un paiement).
        $reservation?->update(['statut_paiement' => 'echoue']);
    }
}
