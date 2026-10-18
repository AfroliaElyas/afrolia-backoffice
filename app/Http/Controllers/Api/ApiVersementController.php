<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersApp;
use Illuminate\Http\Request;

/**
 * Permet à une coiffeuse de renseigner elle-même son numéro Mobile Money de
 * versement, directement depuis l'app. Avant ce contrôleur, seule
 * VersementController::definirInfosVersement (back-office, session admin)
 * pouvait l'enregistrer — aucune route API n'existait pour la coiffeuse
 * elle-même (signalé le 18/10/2026 : l'écran mobile correspondant n'était
 * qu'un Placeholder()).
 */
class ApiVersementController extends Controller
{
    private const OPERATEURS = ['wave', 'orange', 'mtn', 'moov', 'djamo'];

    public function getInfosVersement(Request $request, int $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres informations de versement',
            ], 403);
        }

        $coiffeuse = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'payout_telephone' => $coiffeuse->payout_telephone,
                'payout_operateur' => $coiffeuse->payout_operateur,
            ],
        ]);
    }

    public function definirInfosVersement(Request $request, int $id_utilisateur)
    {
        if ((int) $id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que vos propres informations de versement',
            ], 403);
        }

        $request->validate([
            'payout_telephone' => 'required|string|max:100',
            'payout_operateur' => 'required|string|in:' . implode(',', self::OPERATEURS),
        ], [
            'payout_telephone.required' => 'Veuillez saisir votre numéro Mobile Money.',
            'payout_operateur.required' => 'Veuillez choisir votre opérateur.',
        ]);

        $coiffeuse = UsersApp::where('role', 'hair')->findOrFail($id_utilisateur);
        $coiffeuse->update([
            'payout_telephone' => $request->payout_telephone,
            'payout_operateur' => $request->payout_operateur,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Numéro de versement enregistré.',
            'data' => [
                'payout_telephone' => $coiffeuse->payout_telephone,
                'payout_operateur' => $coiffeuse->payout_operateur,
            ],
        ]);
    }
}
