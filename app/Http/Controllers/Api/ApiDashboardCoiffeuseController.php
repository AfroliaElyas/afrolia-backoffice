<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiDashboardCoiffeuseController extends Controller
{
    /**
     * Dashboard complet d’une coiffeuse
     */
    public function index(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que votre propre tableau de bord'
            ], 403);
        }

        // ✅ Revenus totaux (gains déjà payés)
        $revenusTotaux = DB::table('gains')
            ->where('id_coiffeur', $id_coiffeur)
            ->where('statut', 'paye')
            ->sum('montant_net');

        // ✅ Revenus disponibles (statut = disponible)
        $revenusDisponibles = DB::table('gains')
            ->where('id_coiffeur', $id_coiffeur)
            ->where('statut', 'disponible')
            ->sum('montant_net');

        // ✅ Revenus du mois
        $revenusMois = DB::table('gains')
            ->where('id_coiffeur', $id_coiffeur)
            ->whereMonth('created_at', now()->month)
            ->sum('montant_net');

        // ✅ Revenus du jour
        $revenusJour = DB::table('gains')
            ->where('id_coiffeur', $id_coiffeur)
            ->whereDate('created_at', now()->toDateString())
            ->sum('montant_net');

        // ✅ Total clientes uniques
        $totalClients = DB::table('reservations')
            ->where('id_coiffeur', $id_coiffeur)
            ->distinct('id_client')
            ->count('id_client');

        // ✅ Réservations du mois
        $reservationsMois = DB::table('reservations')
            ->where('id_coiffeur', $id_coiffeur)
            ->whereMonth('date_reservation', now()->month)
            ->count();

        // ✅ Réservations par statut
        $reservationsParStatut = DB::table('reservations')
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->where('id_coiffeur', $id_coiffeur)
            ->groupBy('statut')
            ->pluck('total', 'statut');

        // ✅ Prochaines réservations (3 à venir)
        $prochainesReservations = DB::table('reservations as r')
            ->join('users_app as u', 'u.id_user_app', '=', 'r.id_client')
            ->join('services as s', 's.id_service', '=', 'r.id_service')
            ->where('r.id_coiffeur', $id_coiffeur)
            ->where('r.date_reservation', '>=', now()->toDateString())
            ->whereIn('r.statut', ['confirmee', 'en_attente'])
            ->orderBy('r.date_reservation', 'asc')
            ->limit(3)
            ->select(
                'r.id_reservation',
                'r.date_reservation',
                'r.heure_reservation',
                'r.statut',
                's.description as service',
                'u.nom as client_nom',
                'u.prenom as client_prenom'
            )
            ->get();

        // ✅ Note moyenne & total avis
        $avisStats = DB::table('reviews')
            ->where('id_stylist', $id_coiffeur)
            ->select(
                DB::raw('ROUND(AVG(rating),1) as moyenne_notes'),
                DB::raw('COUNT(id_review) as total_avis')
            )
            ->first();

        // ✅ Réponse finale
        return response()->json([
            'success' => true,
            'message' => 'Données du tableau de bord de la coiffeuse',
            'data' => [
                'revenus_totaux' => $revenusTotaux,
                'revenus_disponibles' => $revenusDisponibles,
                'revenus_mois' => $revenusMois,
                'revenus_jour' => $revenusJour,
                'total_clients' => $totalClients,
                'reservations_mois' => $reservationsMois,
                'reservations_par_statut' => $reservationsParStatut,
                'prochaines_reservations' => $prochainesReservations,
                'moyenne_notes' => $avisStats->moyenne_notes ?? 0,
                'total_avis' => $avisStats->total_avis ?? 0,
            ]
        ]);
    }
}
