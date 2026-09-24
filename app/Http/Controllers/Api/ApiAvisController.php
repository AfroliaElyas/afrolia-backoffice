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
        $avis = Reviews::where('id_coiffeuse', $id_coiffeuse)
            ->with(['utilisateur'])
            ->orderBy('id_avis', 'desc')
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
            'note' => 'required|numeric|min:1|max:5',
            'commentaire' => 'nullable|string',
            'id_coiffeuse' => 'required|integer',
            'id_reservation' => 'nullable|integer',
        ]);

        // Un avis est toujours publié par l'utilisateur authentifié, jamais
        // par un id_utilisateur transmis par le client.
        $validated['id_utilisateur'] = $request->user()->id_user_app;

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

        if ((int) $avis->id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que vos propres avis'
            ], 403);
        }

        $avis->update($request->all());

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

        if ((int) $avis->id_utilisateur !== (int) $request->user()->id_user_app) {
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
