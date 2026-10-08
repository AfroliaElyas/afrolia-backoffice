<?php

namespace App\Http\Controllers;

use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemboursementController extends Controller
{
    public function __construct(private readonly StripeGatewayInterface $stripeGateway)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        // En attente : réservation annulée, déjà payée, pas encore remboursée
        $attente = DB::table('reservations')
            ->where('statut', 'annulee')
            ->where('statut_paiement', 'paye')
            ->count();

        $remboursements = DB::table('reservations')
            ->join('users_app as clients', 'reservations.id_client', '=', 'clients.id_user_app')
            ->join('users_app as coiffeuses', 'reservations.id_coiffeur', '=', 'coiffeuses.id_user_app')
            ->where('reservations.statut', 'annulee')
            ->whereIn('reservations.statut_paiement', ['paye', 'rembourse'])
            ->select(
                'reservations.*',
                'clients.name as client_prenom',
                'clients.last_name as client_nom',
                'clients.phone as client_phone',
                'clients.photo as client_photo',
                'coiffeuses.name as coiffeuse_prenom',
                'coiffeuses.last_name as coiffeuse_nom',
                'coiffeuses.phone as coiffeuse_phone',
                'coiffeuses.photo as coiffeuse_photo'
            )
            ->orderByDesc('reservations.annule_le')
            ->get();

        return view('publicites.remboursement', compact('attente', 'remboursements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Traiter un remboursement : rembourse réellement le paiement auprès du
     * prestataire avant de marquer la réservation comme remboursée — ne
     * jamais se contenter de changer le statut en base sans que l'argent
     * soit réellement retourné au client.
     */
    public function traiter(string $id)
    {
        $reservation = DB::table('reservations')->where('id_reservation', $id)->first();

        if (!$reservation) {
            return back()->withErrors(['Réservation introuvable.']);
        }

        $paiement = DB::table('paiements')
            ->where('id_reservation', $id)
            ->where('status', 'succeeded')
            ->latest('id_paiement')
            ->first();

        if (!$paiement) {
            return back()->withErrors(['Aucun paiement réussi trouvé pour cette réservation.']);
        }

        if ($paiement->payment_method === 'stripe') {
            if (!$paiement->payment_intent_id) {
                return back()->withErrors(['Ce paiement Stripe ne référence aucun PaymentIntent, remboursement impossible automatiquement.']);
            }

            try {
                $this->stripeGateway->refund($paiement->payment_intent_id, (float) $paiement->amount);
            } catch (\Throwable $e) {
                Log::error('Échec du remboursement Stripe', ['id_paiement' => $paiement->id_paiement, 'erreur' => $e->getMessage()]);

                return back()->withErrors(['Le remboursement Stripe a échoué : ' . $e->getMessage()]);
            }
        } else {
            // Mobile Money : aucune intégration réelle n'existe encore
            // (voir GenericMobileMoneyGateway) — impossible de rembourser
            // automatiquement tant que Jèko n'est pas branché.
            return back()->withErrors([
                'Ce paiement a été effectué par Mobile Money : aucun remboursement automatique n\'est disponible pour le moment. Contactez le client et traitez le remboursement directement avec l\'opérateur.',
            ]);
        }

        DB::table('reservations')
            ->where('id_reservation', $id)
            ->update(['statut_paiement' => 'rembourse']);

        DB::table('paiements')
            ->where('id_paiement', $paiement->id_paiement)
            ->update([
                'status'       => 'refunded',
                'processed_at' => now(),
            ]);

        return back()->with('succes', 'Le remboursement a été traité et le client remboursé sur Stripe.');
    }

    /**
     * Rejeter une demande de remboursement.
     */
    public function rejeter(Request $request, string $id)
    {
        $request->validate([
            'raison' => 'required|string|max:500',
        ], [
            'raison.required' => 'Veuillez indiquer la raison du rejet.',
        ]);

        $reservation = DB::table('reservations')->where('id_reservation', $id)->first();

        if (!$reservation) {
            return back()->withErrors(['Réservation introuvable.']);
        }

        DB::table('reservations')
            ->where('id_reservation', $id)
            ->update([
                'notes' => trim(($reservation->notes ? $reservation->notes . ' | ' : '') . 'Remboursement rejeté: ' . $request->raison),
            ]);

        return back()->with('succes', 'La demande de remboursement a été rejetée.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
