<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\AbonnementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiReservationsController extends Controller
{
    public function __construct(
        private readonly AbonnementService $abonnements
    ) {
    }

    // ✅ 1. Liste des réservations d’un client
    public function getReservationByUser(Request $request, $id)
    {
        if ((int) $id !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres réservations'
            ], 403);
        }

        $reservations = Reservations::join('users_app as coiffeuses', 'reservations.id_coiffeur', '=', 'coiffeuses.id_user_app')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->select(
                'reservations.id_reservation',
                'reservations.numero_reservation',
                'reservations.date_reservation',
                'reservations.heure_reservation',
                'reservations.statut',
                'reservations.montant_total',
                'coiffeuses.name as nom_coiffeuse',
                'coiffeuses.last_name as prenom_coiffeuse',
                'coiffeuses.photo as photo_coiffeuse',
                'specialites.libelle as nom_service'
            )
            ->where('reservations.id_client', $id)
            ->orderBy('reservations.date_reservation', 'desc')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune réservation trouvée pour cet utilisateur'
            ], 404);
        }

        $reservations->transform(function ($reservation) {
            $reservation->statut_label = match ($reservation->statut) {
                'confirmee' => 'Confirmée',
                'en_attente' => 'En attente',
                'en_cours' => 'En cours',
                'terminee' => 'Terminée',
                'annulee' => 'Annulée',
                'no_show' => 'Non honorée',
                default => ucfirst($reservation->statut),
            };
            $reservation->montant_affiche = number_format($reservation->montant_total, 0, ',', ' ') . ' CFA';
            return $reservation;
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des réservations de l’utilisateur récupérée avec succès',
            'data' => $reservations
        ]);
    }

    // ✅ 2. Liste des réservations d’un coiffeur
    public function getReservationsByCoiffeuse(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres réservations'
            ], 403);
        }

        $reservations = Reservations::join('users_app as clients', 'reservations.id_client', '=', 'clients.id_user_app')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->select(
                'reservations.id_reservation',
                'reservations.numero_reservation',
                'reservations.date_reservation',
                'reservations.heure_reservation',
                'reservations.statut',
                'reservations.montant_total',
                'clients.name as nom_client',
                'clients.last_name as prenom_client',
                'clients.phone as telephone',
                'clients.photo as photo_client',
                'specialites.libelle as nom_service'
            )
            ->where('reservations.id_coiffeur', $id_coiffeur)
            ->orderBy('reservations.date_reservation', 'desc')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune réservation trouvée pour ce coiffeur'
            ], 404);
        }

        $reservations->transform(function ($reservation) {
            $reservation->statut_label = match ($reservation->statut) {
                'confirmee' => 'Confirmée',
                'en_attente' => 'En attente',
                'en_cours' => 'En cours',
                'terminee' => 'Terminée',
                'annulee' => 'Annulée',
                'no_show' => 'Non honorée',
                default => ucfirst($reservation->statut),
            };
            $reservation->montant_affiche = number_format($reservation->montant_total, 0, ',', ' ') . ' CFA';
            return $reservation;
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des réservations du coiffeur récupérée avec succès',
            'data' => $reservations
        ]);
    }

    // ✅ 2. Création d’une réservation
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_coiffeur' => 'required|integer',
            'id_service' => 'required|integer',
            'date_reservation' => 'required|date',
            'heure_reservation' => 'required',
            'prix_service' => 'required|numeric',
            'montant_commission' => 'sometimes|numeric',
            'montant_total' => 'sometimes|numeric',
            'methode_paiement' => 'required|string|in:stripe,mobile_money,cash',
            'notes' => 'nullable|string',
        ]);

        // La réservation est toujours créée pour l'utilisateur authentifié,
        // jamais pour un id_client transmis par le client.
        $validated['id_client'] = $request->user()->id_user_app;

        // La commission doit toujours refléter la formule d'abonnement
        // actuelle de la coiffeuse (gratuit/standard/premium) : on l'ignore
        // si le client en envoie une et on la recalcule ici, jamais l'inverse.
        $coiffeuse = UsersApp::find($validated['id_coiffeur']);
        $taux = $coiffeuse
            ? $this->abonnements->tauxCommissionPourCoiffeuse($coiffeuse)
            : $this->abonnements->tauxCommission('gratuit');

        $validated['montant_commission'] = round($validated['prix_service'] * $taux, 2);
        $validated['montant_total'] = $validated['prix_service'] + $validated['montant_commission'];

        // Génération du numero_reservation unique
        do {
            $numero_reservation = 'RES-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Reservations::where('numero_reservation', $numero_reservation)->exists());

        $validated['numero_reservation'] = $numero_reservation;

        $reservation = Reservations::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Réservation créée avec succès',
            'data' => $reservation
        ], 201);
    }

    // ✅ 3. Mise à jour d’une réservation
    public function update(Request $request, $id)
    {
        $reservation = Reservations::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], 404);
        }

        if ((int) $reservation->id_client !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que vos propres réservations'
            ], 403);
        }

        $validated = $request->validate([
            'date_reservation' => 'sometimes|date',
            'heure_reservation' => 'sometimes',
            'statut' => 'sometimes|in:en_attente,confirmee,en_cours,terminee,annulee,no_show',
            'prix_service' => 'sometimes|numeric',
            'montant_commission' => 'sometimes|numeric',
            'montant_total' => 'sometimes|numeric',
            'statut_paiement' => 'sometimes|in:en_attente,paye,rembourse,echoue',
            'methode_paiement' => 'sometimes|in:stripe,mobile_money,cash',
            'notes' => 'nullable|string',
            'raison_annulation' => 'nullable|string',
            'annule_par' => 'nullable|in:client,coiffeur,admin'
        ]);

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Réservation mise à jour avec succès',
            'data' => $reservation
        ]);
    }

    // ✅ 4. Suppression d’une réservation
    public function destroy(Request $request, $id)
    {
        $reservation = Reservations::find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], 404);
        }

        if ((int) $reservation->id_client !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez supprimer que vos propres réservations'
            ], 403);
        }

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Réservation supprimée avec succès'
        ]);
    }

    // ✅ 5. Confirmer une réservation
    public function confirmerReservation(Request $request, $id_reservation)
    {
        $reservation = Reservations::where('id_reservation', $id_reservation)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation introuvable ou non autorisée'
            ], 404);
        }

        $reservation->update([
            'statut' => 'confirmee',
            'confirme_le' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réservation confirmée avec succès',
            'data' => $reservation
        ]);
    }

    // 🚫 6. Refuser (Annuler) une réservation
    public function refuserReservation(Request $request, $id_reservation)
    {
        $reservation = Reservations::where('id_reservation', $id_reservation)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation introuvable ou non autorisée'
            ], 404);
        }

        $reservation->update([
            'statut' => 'annulee',
            'annule_le' => now(),
            'annule_par' => 'coiffeur',
            'raison_annulation' => $request->input('raison', 'Aucune raison spécifiée')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée avec succès',
            'data' => $reservation
        ]);
    }

    // 🏁 7. Terminer une réservation
    public function terminerReservation(Request $request, $id_reservation)
    {
        $reservation = Reservations::where('id_reservation', $id_reservation)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation introuvable ou non autorisée'
            ], 404);
        }

        if (!in_array($reservation->statut, ['confirmee', 'en_cours'])) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de terminer cette réservation. Elle doit être confirmée ou en cours.'
            ], 400);
        }

        $reservation->update([
            'statut' => 'terminee',
            'termine_le' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réservation marquée comme terminée avec succès',
            'data' => $reservation
        ]);
    }

    public function recentReservations(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres réservations'
            ], 403);
        }

        // Récupère les 3 réservations les plus récentes pour un coiffeur donné
        $reservations = Reservations::where('id_coiffeur', $id_coiffeur)
            ->orderBy('date_reservation', 'desc')
            ->orderBy('heure_reservation', 'desc')
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }

    public function reservationStatsByCoiffeur(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres statistiques'
            ], 403);
        }

        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

        // Nombre total de clients avec réservations terminées pour ce coiffeur
        $totalClientsTerminees = Reservations::where('statut', 'terminee')
            ->where('id_coiffeur', $id_coiffeur)
            ->distinct('id_client')
            ->count('id_client');

        // Revenu du jour pour réservations terminées
        $revenuJour = Reservations::where('statut', 'terminee')
            ->where('id_coiffeur', $id_coiffeur)
            ->whereDate('date_reservation', $today)
            ->sum('montant_total');

        // Revenu du mois en cours pour réservations terminées
        $revenuMois = Reservations::where('statut', 'terminee')
            ->where('id_coiffeur', $id_coiffeur)
            ->whereBetween('date_reservation', [$startOfMonth, $today])
            ->sum('montant_total');

        // Revenu des réservations en attente
        $revenuAttente = Reservations::where('statut', 'en_attente')
            ->where('id_coiffeur', $id_coiffeur)
            ->sum('montant_total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_clients_termines' => $totalClientsTerminees,
                'revenu_jour' => $revenuJour,
                'revenu_mois' => $revenuMois,
                'revenu_en_attente' => $revenuAttente
            ]
        ]);
    }

    public function topServices(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres statistiques'
            ], 403);
        }

        $topServices = DB::table('reservations as r')
            ->select(
                's.id_service',
                's.description',
                's.prix',
                's.minute',
                'sp.libelle as specialite',
                DB::raw('COUNT(r.id_reservation) as nombre_reservations')
            )
            ->join('services as s', 'r.id_service', '=', 's.id_service')
            ->join('specialites as sp', 's.id_speciale', '=', 'sp.id_specialite')
            ->where('r.id_coiffeur', $id_coiffeur)
            ->where('r.statut', 'terminee')
            ->groupBy('s.id_service', 's.description', 's.prix', 's.minute', 'sp.libelle')
            ->orderByDesc('nombre_reservations')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topServices
        ]);
    }
}
