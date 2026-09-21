<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersApp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSalonController extends Controller
{
    public function getCoiffeursAvecStatut()
    {
        $now = Carbon::now();
        $dateActuelle = $now->format('Y-m-d');
        $heureActuelle = $now->format('H:i:s');

        $coiffeurs = UsersApp::select(
            'users_app.id_user_app',
            'users_app.photo',
            'users_app.name',
            'users_app.last_name',
            'users_app.commune',
            'users_app.experience',
            'users_app.formule_abonnement',

            DB::raw('COALESCE(AVG(reviews.rating), 0) as moyenne_note'),
            DB::raw('COUNT(DISTINCT reviews.id_review) as nombre_avis'),

            DB::raw('GROUP_CONCAT(DISTINCT specialites.libelle SEPARATOR ", ") as specialites'),
            DB::raw('MIN(services.prix) as prix_min'),
            DB::raw('MAX(services.prix) as prix_max'),

            // 🔥 NOUVEAU : sous-requête sans dépendance à GROUP BY
            DB::raw("
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM reservations r
                    JOIN services s2 ON s2.id_service = r.id_service
                    WHERE r.id_coiffeur = users_app.id_user_app
                      AND r.date_reservation = '$dateActuelle'
                      AND r.statut IN ('confirmee', 'en_cours')
                      AND r.heure_reservation <= '$heureActuelle'
                      AND ADDTIME(r.heure_reservation, SEC_TO_TIME(s2.minute * 60)) > '$heureActuelle'
                )
                THEN 'Occupé'
                ELSE 'Disponible'
            END as statut_disponibilite
        ")
        )
            ->leftJoin('reviews', function ($join) {
                $join->on('users_app.id_user_app', '=', 'reviews.id_stylist')
                    ->where('reviews.status', '=', 'approved');
            })
            ->leftJoin('services', 'users_app.id_user_app', '=', 'services.id_utilisateur')
            ->leftJoin('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->where('users_app.role', 'hair')
            ->where('users_app.statut', 'Active')
            ->groupBy(
                'users_app.id_user_app',
                'users_app.photo',
                'users_app.name',
                'users_app.last_name',
                'users_app.commune',
                'users_app.experience',
                'users_app.formule_abonnement'
            )
            // Les coiffeuses Premium apparaissent en premier, puis Standard,
            // puis Gratuit ; à palier égal, la meilleure note passe devant.
            ->orderByRaw("FIELD(users_app.formule_abonnement, 'premium', 'standard', 'gratuit')")
            ->orderByRaw('moyenne_note DESC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coiffeurs->map(function ($coiffeur) {
                return [
                    'id' => $coiffeur->id_user_app,
                    'photo' => $coiffeur->photo ? url($coiffeur->photo) : "",
                    'nom_complet' => $coiffeur->name . ' ' . $coiffeur->last_name,
                    'commune' => $coiffeur->commune,
                    'experience' => $coiffeur->experience,
                    'note' => round($coiffeur->moyenne_note, 1),
                    'nombre_avis' => $coiffeur->nombre_avis,
                    'specialites' => explode(', ', $coiffeur->specialites),
                    'prix_range' => number_format($coiffeur->prix_min, 0, ',', ' ') . ' - ' .
                        number_format($coiffeur->prix_max, 0, ',', ' ') . ' FCFA',
                    'statut' => $coiffeur->statut_disponibilite,
                    'est_premium' => $coiffeur->formule_abonnement === 'premium',
                ];
            })
        ]);
    }


    public function getProfilCoiffeur($id_coiffeur)
    {
        $coiffeur = UsersApp::select(
            'users_app.id_user_app',
            'users_app.photo',
            'users_app.name',
            'users_app.last_name',
            'users_app.commune',
            'users_app.experience',
            'users_app.presentation',
            DB::raw('COALESCE(AVG(reviews.rating), 0) as moyenne_note'),
            DB::raw('COUNT(DISTINCT reviews.id_review) as nombre_avis'),
            DB::raw('GROUP_CONCAT(DISTINCT specialites.libelle SEPARATOR " & ") as specialites'),
            DB::raw('COUNT(DISTINCT CASE WHEN reservations.statut = "terminee" THEN reservations.id_client END) as total_clients')
        )
            ->leftJoin('reviews', function ($join) {
                $join->on('users_app.id_user_app', '=', 'reviews.id_stylist')
                    ->where('reviews.status', '=', 'approved');
            })
            ->leftJoin('services', 'users_app.id_user_app', '=', 'services.id_utilisateur')
            ->leftJoin('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->leftJoin('reservations', function ($join) {
                $join->on('users_app.id_user_app', '=', 'reservations.id_coiffeur');
            })
            ->where('users_app.id_user_app', $id_coiffeur)
            ->where('users_app.role', 'hair')
            ->groupBy(
                'users_app.id_user_app',
                'users_app.photo',
                'users_app.name',
                'users_app.last_name',
                'users_app.commune',
                'users_app.experience',
                'users_app.presentation'
            )
            ->first();

        if (!$coiffeur) {
            return response()->json([
                'success' => false,
                'message' => 'Coiffeur non trouvé'
            ], 404);
        }

        // Calculer le taux de satisfaction (% de notes 4 et 5 étoiles)
        $tauxSatisfaction = 0;
        if ($coiffeur->nombre_avis > 0) {
            $notesPositives = DB::table('reviews')
                ->where('id_stylist', $id_coiffeur)
                ->where('status', 'approved')
                ->whereIn('rating', [4, 5])
                ->count();

            $tauxSatisfaction = round(($notesPositives / $coiffeur->nombre_avis) * 100);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $coiffeur->id_user_app,
                'photo' => $coiffeur->photo ? url($coiffeur->photo) : "",
                'nom_complet' => $coiffeur->name . ' ' . $coiffeur->last_name,
                'localisation' => $coiffeur->commune,
                'note' => round($coiffeur->moyenne_note, 1),
                'nombre_avis' => $coiffeur->nombre_avis,
                'experience' => $coiffeur->experience,
                'specialites' => strtolower($coiffeur->specialites),
                'presentation' => $coiffeur->presentation,
                'statistiques' => [
                    'clients' => $coiffeur->total_clients,
                    'satisfaction' => $tauxSatisfaction . '%'
                ]
            ]
        ]);
    }
}
