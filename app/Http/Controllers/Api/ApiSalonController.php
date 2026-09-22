<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersApp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSalonController extends Controller
{
    public function getCoiffeursAvecStatut(Request $request)
    {
        $now = Carbon::now();
        $dateActuelle = $now->format('Y-m-d');
        $heureActuelle = $now->format('H:i:s');

        $lat = $request->filled('lat') ? (float) $request->query('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->query('lng') : null;
        $avecPosition = $lat !== null && $lng !== null;
        $rayonKm = $avecPosition
            ? (float) ($request->query('rayon_km') ?? $this->rayonRechercheParDefaut())
            : null;

        $requete = UsersApp::select(
            'users_app.id_user_app',
            'users_app.photo',
            'users_app.name',
            'users_app.last_name',
            'users_app.commune',
            'users_app.experience',
            'users_app.formule_abonnement',
            'users_app.latitude',
            'users_app.longitude',
            'users_app.deplacement_domicile',

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
                'users_app.formule_abonnement',
                'users_app.latitude',
                'users_app.longitude',
                'users_app.deplacement_domicile'
            );

        if ($avecPosition) {
            // Distance à vol d'oiseau en km (formule de Haversine). On utilise
            // des fonctions trigonométriques standard (SIN/COS/ACOS/RADIANS)
            // plutôt que ST_Distance_Sphere/POINT (MySQL-only) pour que la
            // requête reste portable et testable sous SQLite.
            //
            // L'expression est répétée (avec ses propres `?`) plutôt que de
            // référencer l'alias "distance_km" dans la clause du rayon
            // ci-dessous : réutiliser un alias à l'intérieur d'un CASE combiné
            // à un paramètre lié donne un ordre incorrect sous SQLite (testé),
            // alors qu'une utilisation "nue" de l'alias (IS NULL / ASC) reste
            // fiable sur les deux moteurs.
            $expressionDistance = "(CASE
                WHEN users_app.latitude IS NULL OR users_app.longitude IS NULL THEN NULL
                ELSE 6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(users_app.latitude)) * COS(RADIANS(users_app.longitude) - RADIANS(?))
                    + SIN(RADIANS(?)) * SIN(RADIANS(users_app.latitude))
                )
            END)";

            $requete->selectRaw("ROUND($expressionDistance, 2) as distance_km", [$lat, $lng, $lat]);

            // Ordre demandé : (1) Premium à proximité, (2) autres à proximité,
            // (3) Premium hors rayon triées par distance, (4) autres hors
            // rayon triées par distance. Une coiffeuse sans position connue
            // est traitée comme "hors rayon" (on ne peut pas prouver sa
            // proximité, et une comparaison avec NULL n'est jamais vraie) et
            // placée en fin de son groupe.
            $requete
                // "(? + 0)" force une comparaison numérique : lié tel quel, PDO
                // envoie le paramètre en TEXT et SQLite (utilisé par les
                // tests) compare alors par classe de stockage (REAL < TEXT
                // toujours), pas par valeur — ce qui fausserait le classement.
                ->orderByRaw("CASE WHEN $expressionDistance <= (? + 0) THEN 0 ELSE 1 END", [$lat, $lng, $lat, $rayonKm])
                ->orderByRaw("CASE WHEN users_app.formule_abonnement = 'premium' THEN 0 ELSE 1 END")
                ->orderByRaw('distance_km IS NULL')
                ->orderByRaw('distance_km ASC')
                ->orderByRaw('moyenne_note DESC');
        } else {
            // Pas de position transmise (permission refusée, première visite) :
            // on garde le tri par palier d'abonnement seul.
            $requete
                ->orderByRaw("FIELD(users_app.formule_abonnement, 'premium', 'standard', 'gratuit')")
                ->orderByRaw('moyenne_note DESC');
        }

        $coiffeurs = $requete->get();

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
                    'distance_km' => $coiffeur->distance_km ?? null,
                    'deplacement_domicile' => (bool) $coiffeur->deplacement_domicile,
                ];
            })
        ]);
    }

    private function rayonRechercheParDefaut(): float
    {
        $valeur = DB::table('parametres')->where('cle', 'rayon_recherche_defaut_km')->value('valeur');

        return $valeur !== null ? (float) $valeur : 8.0;
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
