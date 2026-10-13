<?php

namespace App\Http\Controllers;

use App\Models\InscriptionLancement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LancementController extends Controller
{
    /**
     * Enregistre une adresse e-mail pour être prévenue du lancement, depuis
     * le formulaire de la page d'accueil publique. Idempotent : une adresse
     * déjà inscrite n'est pas une erreur.
     */
    public function inscrire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:150',
        ], [
            'email.required' => "Merci de renseigner votre adresse e-mail.",
            'email.email' => "Cette adresse e-mail n'est pas valide.",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('email'),
            ], 422);
        }

        $email = $request->input('email');

        if (InscriptionLancement::where('email', $email)->exists()) {
            return response()->json([
                'success' => true,
                'message' => "Vous êtes déjà inscrite, on vous préviendra dès le lancement !",
            ]);
        }

        InscriptionLancement::create(['email' => $email]);

        return response()->json([
            'success' => true,
            'message' => "Merci ! On vous préviendra dès le lancement.",
        ]);
    }
}
