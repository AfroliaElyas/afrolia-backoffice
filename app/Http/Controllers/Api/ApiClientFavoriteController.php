<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiClientFavoriteController extends Controller
{
    // ✅ Ajouter un favori
    public function addFavorite(Request $request)
    {
        $validated = $request->validate([
            'stylist_id' => 'required|integer',
        ]);

        $validated['client_id'] = $request->user()->id_user_app;

        $exists = ClientFavorite::where('client_id', $validated['client_id'])
            ->where('stylist_id', $validated['stylist_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Cette coiffeuse est déjà dans vos favoris'
            ], 400);
        }

        $favorite = ClientFavorite::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coiffeuse ajoutée aux favoris avec succès',
            'data' => $favorite
        ], 201);
    }

    // ✅ Supprimer un favori
    public function removeFavorite(Request $request)
    {
        $validated = $request->validate([
            'stylist_id' => 'required|integer',
        ]);

        $validated['client_id'] = $request->user()->id_user_app;

        $deleted = ClientFavorite::where('client_id', $validated['client_id'])
            ->where('stylist_id', $validated['stylist_id'])
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Favori introuvable'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coiffeuse supprimée des favoris'
        ]);
    }

    // ✅ Liste des coiffeuses favorites avec note moyenne et avis
    public function getFavoritesByClient(Request $request, $client_id)
    {
        if ((int) $client_id !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres favoris'
            ], 403);
        }

        $favorites = ClientFavorite::where('client_id', $client_id)
            ->join('users_app as u', 'u.id_user_app', '=', 'client_favorites.stylist_id')
            ->leftJoin('reviews as r', 'r.id_stylist', '=', 'u.id_user_app')
            ->select(
                'u.id_user_app',
                'u.nom',
                'u.prenom',
                'u.photo_profil',
                DB::raw('COALESCE(ROUND(AVG(r.rating), 1), 0) as moyenne_notes'),
                DB::raw('COUNT(r.id_review) as nombre_avis')
            )
            ->groupBy('u.id_user_app', 'u.nom', 'u.prenom', 'u.photo_profil')
            ->get();

        if ($favorites->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune coiffeuse favorite trouvée pour ce client'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des coiffeuses favorites avec leurs notes',
            'data' => $favorites
        ]);
    }

    // ✅ Vérifier si une coiffeuse est favorite
    public function isFavorite(Request $request)
    {
        $validated = $request->validate([
            'stylist_id' => 'required|integer',
        ]);

        $validated['client_id'] = $request->user()->id_user_app;

        $exists = ClientFavorite::where('client_id', $validated['client_id'])
            ->where('stylist_id', $validated['stylist_id'])
            ->exists();

        return response()->json([
            'success' => true,
            'is_favorite' => $exists
        ]);
    }
}
