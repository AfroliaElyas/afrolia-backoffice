<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reviews;
use Illuminate\Http\Request;

class ApiAvisController extends Controller
{
    // ✅ 1. Récupérer tous les avis pour une coiffeuse
    public function getAvisByCoiffeuse($id_coiffeuse)
    {
        $avis = Reviews::where('id_stylist', $id_coiffeuse)
            ->with(['client:id_user_app,name,last_name,photo'])
            ->orderBy('id_review', 'desc')
            ->get();

        if ($avis->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun avis trouvé pour cette coiffeuse'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des avis de la coiffeuse',
            'data' => $avis
        ]);
    }

    // ✅ 2. Créer un avis
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'id_stylist' => 'required|integer|exists:users_app,id_user_app',
            // La table impose id_reservation en base (une coiffeuse ne peut
            // être notée qu'au titre d'une réservation réelle) : la
            // validation doit refléter cette contrainte, pas la contredire.
            'id_reservation' => 'required|integer|exists:reservations,id_reservation',
        ]);

        // Un avis est toujours publié par l'utilisateur authentifié, jamais
        // par un id_client transmis par le client.
        $validated['id_client'] = $request->user()->id_user_app;

        $avis = Reviews::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avis enregistré avec succès',
            'data' => $avis
        ], 201);
    }

    // ✅ 3. Mise à jour d’un avis
    public function update(Request $request, $id_avis)
    {
        $avis = Reviews::find($id_avis);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable'
            ], 404);
        }

        if ((int) $avis->id_client !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que vos propres avis'
            ], 403);
        }

        // Seuls la note et le commentaire sont modifiables : pas question de
        // laisser l'auteur d'un avis réassigner id_stylist/id_reservation ou
        // s'auto-approuver via status/is_verified.
        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $avis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avis mis à jour avec succès',
            'data' => $avis
        ]);
    }

    // ✅ 4. Suppression d’un avis
    public function destroy(Request $request, $id_avis)
    {
        $avis = Reviews::find($id_avis);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable'
            ], 404);
        }

        if ((int) $avis->id_client !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez supprimer que vos propres avis'
            ], 403);
        }

        $avis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Avis supprimé avec succès'
        ]);
    }
}
