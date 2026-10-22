<?php

namespace App\Http\Controllers;

use App\Services\RecuperationCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pages web publiques ouvertes depuis le lien reçu par e-mail (vérification
 * d'adresse, réinitialisation de mot de passe) : la personne clique depuis
 * sa boîte mail, pas depuis l'application mobile.
 */
class RecuperationCompteController extends Controller
{
    public function __construct(private readonly RecuperationCompteService $service)
    {
    }

    /**
     * N'affiche qu'une page de confirmation (avec un bouton à cliquer) sans
     * consommer le jeton : certains clients mail (Outlook/Hotmail) ouvrent
     * automatiquement les liens reçus pour les scanner, ce qui invaliderait
     * un lien à usage unique avant même que la personne ne le voie.
     */
    public function verifierEmail(string $token)
    {
        $valide = $this->service->jetonVerificationValide($token);

        return view('recuperation.verification-email', [
            'valide' => $valide,
            'reussi' => false,
            'token' => $token,
        ]);
    }

    /**
     * Ce POST, déclenché uniquement par un vrai clic sur le bouton de la
     * page de confirmation, consomme le jeton pour de bon.
     */
    public function confirmerVerificationEmail(string $token)
    {
        $reussi = $this->service->verifierEmailAvecJeton($token);

        return view('recuperation.verification-email', [
            'valide' => $reussi,
            'reussi' => $reussi,
            'token' => $token,
        ]);
    }

    public function formulaireReinitialisation(string $token)
    {
        $valide = $this->service->jetonReinitialisationValide($token);

        return view('recuperation.reinitialisation-mot-de-passe', [
            'valide' => $valide,
            'token' => $token,
            'reussi' => false,
        ]);
    }

    public function traiterReinitialisation(Request $request, string $token)
    {
        $validator = Validator::make($request->all(), [
            'mot_de_passe' => 'required|string|min:8|confirmed',
        ], [
            'mot_de_passe.required' => 'Veuillez saisir un nouveau mot de passe.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        if ($validator->fails()) {
            return view('recuperation.reinitialisation-mot-de-passe', [
                'valide' => $this->service->jetonReinitialisationValide($token),
                'token' => $token,
                'reussi' => false,
                'erreurs' => $validator->errors(),
            ]);
        }

        $reussi = $this->service->reinitialiserAvecJeton($token, $request->input('mot_de_passe'));

        return view('recuperation.reinitialisation-mot-de-passe', [
            'valide' => $reussi,
            'token' => $token,
            'reussi' => $reussi,
        ]);
    }
}
