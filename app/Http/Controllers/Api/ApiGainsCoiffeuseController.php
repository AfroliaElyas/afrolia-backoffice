<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gains;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiGainsCoiffeuseController extends Controller
{
    // ✅ 1. Récupération des statistiques de gains d'une coiffeuse
    public function getGainsByUser(Request $request, $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres gains'
            ], 403);
        }

        $now = Carbon::now();

        // Revenu total
        $revenuTotal = Gains::where('id_coiffeur', $id_utilisateur)
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        // Revenu du mois courant
        $revenuMois = Gains::where('id_coiffeur', $id_utilisateur)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        // Revenu du mois précédent
        $revenuMoisPrecedent = Gains::where('id_coiffeur', $id_utilisateur)
            ->whereMonth('created_at', $now->copy()->subMonth()->month)
            ->whereYear('created_at', $now->year)
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        // Évolution mensuelle
        $evolution = $revenuMoisPrecedent > 0
            ? (($revenuMois - $revenuMoisPrecedent) / $revenuMoisPrecedent) * 100
            : 0;

        // Revenu hebdomadaire
        $revenuSemaine = Gains::where('id_coiffeur', $id_utilisateur)
            ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        // Revenu du jour
        $revenuJour = Gains::where('id_coiffeur', $id_utilisateur)
            ->whereDate('created_at', Carbon::today())
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        // Paiements en attente
        $revenuAttente = Gains::where('id_coiffeur', $id_utilisateur)
            ->where('statut', 'en_attente')
            ->sum('montant_net');

        return response()->json([
            'success' => true,
            'message' => 'Statistiques de gains de la coiffeuse',
            'data' => [
                'revenu_mois' => round($revenuMois, 0),
                'evolution_mois' => round($evolution, 1),
                'revenu_semaine' => round($revenuSemaine, 0),
                'revenu_jour' => round($revenuJour, 0),
                'revenu_attente' => round($revenuAttente, 0),
                'revenu_total' => round($revenuTotal, 0),
            ]
        ]);
    }

    public function getEvolutionAnnuelle(Request $request, $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres gains'
            ], 403);
        }

        $annee = now()->year;

        // Initialiser les mois (1 à 12)
        $mois = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];

        // Récupération des revenus mensuels
        $gains = Gains::selectRaw('MONTH(created_at) as mois, SUM(montant_net) as total')
            ->where('id_coiffeur', $id_utilisateur)
            ->whereYear('created_at', $annee)
            ->where('statut', '!=', 'en_attente')
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get();

        // Mise en forme des données pour les 12 mois
        $revenus = [];
        foreach ($mois as $num => $nom) {
            $revenu = $gains->firstWhere('mois', $num);
            $revenus[] = [
                'mois' => $nom,
                'montant' => $revenu ? round($revenu->total, 0) : 0
            ];
        }

        // Total annuel
        $totalAnnuel = array_sum(array_column($revenus, 'montant'));

        // Croissance moyenne mensuelle (facultative)
        $croissance = 0;
        $moisNonVides = array_values(array_filter($revenus, fn($r) => $r['montant'] > 0));
        if (count($moisNonVides) > 1) {
            $premier = $moisNonVides[0]['montant'];
            $dernier = end($moisNonVides)['montant'];
            $croissance = (($dernier - $premier) / $premier) * 100;
        }

        return response()->json([
            'success' => true,
            'message' => "Évolution des revenus pour l'année $annee",
            'data' => [
                'annee' => $annee,
                'revenus_par_mois' => $revenus,
                'total_annuel' => round($totalAnnuel, 0),
                'croissance_estimee' => round($croissance, 1)
            ]
        ]);
    }

    public function getRevenusParService(Request $request, $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres gains'
            ], 403);
        }

        // Jointure entre gains, réservations et services
        $gains = DB::table('gains')
            ->join('reservations', 'gains.id_reservation', '=', 'reservations.id_reservation')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->select(
                'services.id_service',
                'services.description',
                DB::raw('COUNT(reservations.id_reservation) as total_reservations'),
                DB::raw('SUM(gains.montant_net) as total_revenu_net'),
                DB::raw('SUM(gains.montant_brut) as total_revenu_brut'),
                DB::raw('SUM(gains.montant_commission) as total_commission')
            )
            ->where('gains.id_coiffeur', $id_utilisateur)
            ->where('gains.statut', '!=', 'en_attente')
            ->groupBy('services.id_service', 'services.description')
            ->orderByDesc('total_revenu_net')
            ->get();

        if ($gains->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun revenu trouvé pour cette coiffeuse'
            ], 404);
        }

        // Calcul du total général
        $totalRevenu = $gains->sum('total_revenu_net');
        $totalReservations = $gains->sum('total_reservations');

        return response()->json([
            'success' => true,
            'message' => 'Revenus par service pour la coiffeuse',
            'data' => [
                'total_revenu' => round($totalRevenu, 0),
                'total_reservations' => $totalReservations,
                'details' => $gains->map(function ($item) {
                    return [
                        'id_service' => $item->id_service,
                        'service' => $item->description,
                        'total_reservations' => (int) $item->total_reservations,
                        'revenu_brut' => round($item->total_revenu_brut, 0),
                        'commission_totale' => round($item->total_commission, 0),
                        'revenu_net' => round($item->total_revenu_net, 0),
                    ];
                })
            ]
        ]);
    }

    // Pas de store()/update()/destroy() ici par conception : un gain ne doit
    // naître que d'un paiement réellement vérifié (voir
    // ApiPaiementsController::markPaiementSucceeded/marquerCommandePayee),
    // jamais d'une requête écrite librement par la coiffeuse elle-même —
    // ces trois actions permettaient de fabriquer ou de réécrire son propre
    // solde sans qu'aucun paiement n'ait eu lieu.
}
