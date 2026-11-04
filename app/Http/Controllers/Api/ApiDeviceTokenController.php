<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Jetons FCM des appareils de l'utilisateur authentifié, utilisés pour
 * lui envoyer des notifications push (réservations, messages,
 * paiements...). Jamais de donnée personnelle ici en dehors du jeton
 * lui-même et de la plateforme.
 */
class ApiDeviceTokenController extends Controller
{
    /**
     * Enregistre (ou réassigne) un jeton d'appareil. Toujours un upsert
     * sur le jeton, jamais une insertion simple : un même jeton FCM peut
     * revenir pour un autre compte (téléphone revendu, réinstallation
     * après déconnexion), et ne doit jamais rester dupliqué ni associé à
     * un ancien propriétaire.
     */
    public function enregistrer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
            'plateforme' => 'required|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'id_user_app' => $request->user()->id_user_app,
                'plateforme' => $request->input('plateforme'),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Jeton enregistré.',
        ]);
    }

    /**
     * Retire un jeton (déconnexion) : ne supprime que si le jeton
     * appartient bien à l'utilisateur authentifié, pour qu'un appareil ne
     * puisse jamais désenregistrer celui d'un autre compte.
     */
    public function supprimer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        DeviceToken::where('token', $request->input('token'))
            ->where('id_user_app', $request->user()->id_user_app)
            ->delete();

        return response()->json(['success' => true]);
    }
}
