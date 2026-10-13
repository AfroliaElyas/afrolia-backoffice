<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersApp;
use App\Services\EnvoiEmailIndisponibleException;
use App\Services\RecuperationCompteService;
use App\Services\SuppressionCompteBloqueeException;
use App\Services\SuppressionCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ApiUtilisateursController extends Controller
{
    public function __construct(
        private readonly RecuperationCompteService $recuperationCompteService,
    ) {
    }

    public function login(Request $request)
    {
        $rules = [
            'login' => 'required',
            'password' => 'required'
        ];

        $messages = [
            'login.required' => 'Veuillez saisir votre telephone.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur = UsersApp::where('phone', $request->login)->first();

        if ($utilisateur && Hash::check($request->password, $utilisateur->password)) {

            if ($utilisateur->statut !== 'Active') {
                return response()->json([
                    'message' => "Votre compte est désactivé. Contactez Afrolia pour la gestion de votre compte."
                ], 401);
            }

            // Un jeton par connexion : permet de révoquer une session précise
            // (déconnexion) sans affecter les autres appareils connectés.
            $token = $utilisateur->createToken('mobile')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'id' => $utilisateur->id_user_app,
                    'nom' => $utilisateur->name,
                    'prenom' => $utilisateur->last_name,
                    'email' => $utilisateur->email ?? "",
                    'presentation' => $utilisateur->presentation ?? "",
                    'phone' => $utilisateur->phone,
                    'adresse' => $utilisateur->adresse ?? "",
                    'experience' => $utilisateur->experience ?? 0,
                    'commune' => $utilisateur->commune ?? "",
                    'role' => $utilisateur->role,
                    'photo' => $utilisateur->photo ? url($utilisateur->photo) : "",
                    'creation' => $utilisateur->created_at,
                    'token' => $token,
                ],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Telephone ou mot de passe incorrect.",
            ], 401);
        }
    }

    public function register(Request $request)
    {
        $rules = [
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'phone' => 'required|string|unique:users_app,phone',
            'email' => 'nullable|email|max:100|unique:users_app,email',
            'password' => 'required',
            'role' => 'required|in:user,hair',
        ];

        $messages = [
            'nom.required' => 'Veuillez saisir votre nom.',
            'prenom.required' => 'Veuillez saisir votre prénom.',
            'phone.required' => 'Veuillez saisir votre numéro de téléphone.',
            'phone.unique' => 'Le numéro de téléphone est deja utilisé.',
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => "L'adresse e-mail est déjà utilisée.",
            'password.required' => 'Veuillez saisir votre mot de passe.',
            'role.required' => 'Veuillez selectionner votre type de conpte.',
            'role.in' => 'Type de compte invalide.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur = new UsersApp();
        $utilisateur->name = $request->nom;
        $utilisateur->last_name = $request->prenom;
        $utilisateur->phone = $request->phone;
        $utilisateur->email = $request->email;
        $utilisateur->role = $request->role;
        $utilisateur->password = Hash::make($request->password);
        if ($utilisateur->save()) {
            $token = $utilisateur->createToken('mobile')->plainTextToken;

            // L'e-mail reste facultatif : son absence, ou l'échec de
            // l'envoi (fournisseur pas encore configuré), ne doit jamais
            // empêcher l'inscription. "emailVerificationEnvoyee" indique
            // seulement si l'app doit proposer de vérifier plus tard.
            $emailVerificationEnvoyee = false;
            if ($utilisateur->email) {
                try {
                    $this->recuperationCompteService->demanderVerificationEmail($utilisateur);
                    $emailVerificationEnvoyee = true;
                } catch (\Throwable $e) {
                    $emailVerificationEnvoyee = false;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Vous êtes inscrit avec succès',
                'data' => [
                    'id' => $utilisateur->id_user_app,
                    'role' => $utilisateur->role,
                    'token' => $token,
                    'emailVerificationEnvoyee' => $emailVerificationEnvoyee,
                ],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite. Veuillez recommencer.",
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    public function update(Request $request, $id)
    {
        if ((int) $id !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez modifier que votre propre compte.",
            ], 403);
        }

        $utilisateur = UsersApp::findOrFail($id);

        $rules = [
            'nom' => 'sometimes|required|string',
            'prenom' => 'sometimes|required|string',
            //'phone' => 'sometimes|required|string|unique:utilisateurs,phone_utilisateur,' . $id,
            //'commune' => 'sometimes|required|integer',
            'photo' => 'nullable|image',
        ];

        $messages = [
            'nom.required' => 'Veuillez saisir votre nom.',
            'prenom.required' => 'Veuillez saisir votre prénom.',
            //'phone.required' => 'Veuillez saisir votre numéro de téléphone.',
            //'phone.unique' => 'Le numéro de téléphone est deja utilisé.',
            //'commune.required' => 'Veuillez sélectionner votre commune.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        if ($request->hasFile('photo')) {
            // supprimer l'ancienne photo si elle existe
            if ($utilisateur->photo) {
                $anciennePhoto = public_path(parse_url($utilisateur->photo, PHP_URL_PATH));
                if (file_exists($anciennePhoto)) {
                    @unlink($anciennePhoto);
                }
            }

            $timestamp = Carbon::now()->format('Ymd_His');
            $photo = $request->file('photo');
            $photoName = 'photo_' . $timestamp . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photo->move(public_path('salon/user'), $photoName);
            $utilisateur->photo = url('afrolia/public/salon/user/' . $photoName);
        }

        // Mise à jour des champs modifiables
        if ($request->filled('nom')) {
            $utilisateur->name = $request->nom;
        }

        if ($request->filled('prenom')) {
            $utilisateur->last_name = $request->prenom;
        }

        // if ($request->filled('phone_utilisateur')) {
        //     $utilisateur->phone_utilisateur = $request->phone;
        // }

        // if ($request->filled('commune_id')) {
        //     $utilisateur->commune_id = $request->commune;
        // }

        if ($utilisateur->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Informations mises à jour avec succès',
                'photo' => $utilisateur->photo ?? '',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Impossible de mettre à jour vos informations. Veuillez recommencer.",
            ], 401);
        }
    }

    /**
     * Ajoute ou change l'adresse e-mail d'une personne déjà connectée, et
     * lui envoie un lien de vérification. L'adresse reste enregistrée mais
     * marquée non vérifiée jusqu'au clic sur ce lien : elle ne peut pas
     * encore servir à réinitialiser un mot de passe.
     */
    public function demanderVerificationEmailCompte(Request $request)
    {
        $utilisateur = $request->user();

        $rules = [
            'email' => 'required|email|max:100|unique:users_app,email,' . $utilisateur->id_user_app . ',id_user_app',
        ];

        $messages = [
            'email.required' => 'Veuillez saisir une adresse e-mail.',
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => "Cette adresse e-mail est déjà utilisée.",
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur->update(['email' => $request->email, 'email_verified_at' => null]);

        try {
            $this->recuperationCompteService->demanderVerificationEmail($utilisateur);
        } catch (EnvoiEmailIndisponibleException $e) {
            return response()->json([
                'success' => false,
                'message' => "L'envoi d'e-mail est temporairement indisponible. Votre adresse a été enregistrée, réessayez la vérification plus tard.",
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'E-mail de vérification envoyé',
        ]);
    }

    /**
     * Demande un lien de réinitialisation de mot de passe par e-mail
     * (parcours 3) : la personne doit avoir une adresse déjà vérifiée.
     */
    public function demanderReinitialisationParEmail(Request $request)
    {
        $rules = [
            'email' => 'required|email|exists:users_app,email',
        ];

        $messages = [
            'email.exists' => "Cette adresse e-mail n'est pas enregistrée.",
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur = UsersApp::where('email', $request->email)->first();

        if ($utilisateur->email_verified_at === null) {
            return response()->json([
                'success' => false,
                'message' => "Cette adresse e-mail n'est pas encore vérifiée. Vérifiez-la depuis votre profil, ou utilisez un code de secours si vous en avez.",
            ], 409);
        }

        try {
            $this->recuperationCompteService->demanderReinitialisationParEmail($utilisateur);
        } catch (EnvoiEmailIndisponibleException $e) {
            return response()->json([
                'success' => false,
                'message' => "L'envoi d'e-mail est temporairement indisponible.",
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'E-mail de réinitialisation envoyé',
        ]);
    }

    /**
     * Génère un nouveau lot de codes de secours pour la personne connectée.
     * Les codes précédents non utilisés sont invalidés : seul ce lot reste
     * valable. Les codes en clair ne sont renvoyés qu'ici, une seule fois.
     */
    public function genererCodesSecours(Request $request)
    {
        $codes = $this->recuperationCompteService->genererCodesSecours($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Codes de secours générés',
            'data' => ['codes' => $codes],
        ]);
    }

    public function statutCodesSecours(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'nombreCodesRestants' => $this->recuperationCompteService->nombreCodesSecoursRestants($request->user()),
            ],
        ]);
    }

    /**
     * État de récupération du compte connecté : adresse e-mail et son
     * statut de vérification, et nombre de codes de secours restants —
     * sans jamais renvoyer le mot de passe ni l'OTP (contrairement à
     * GET /api/user qui expose le modèle brut).
     */
    public function statutSecuriteCompte(Request $request)
    {
        $utilisateur = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $utilisateur->email,
                'emailVerifie' => $utilisateur->email_verified_at !== null,
                'nombreCodesSecoursRestants' => $this->recuperationCompteService->nombreCodesSecoursRestants($utilisateur),
            ],
        ]);
    }

    /**
     * Parcours 4 : réinitialisation sans e-mail ni SMS, via un code de
     * secours généré à l'avance pendant que la personne était connectée.
     * Si le numéro et le code ne correspondent pas ensemble à un code de
     * secours non utilisé, rien n'est réinitialisé : la possession du bon
     * couple téléphone + code est la seule preuve acceptée ici.
     */
    public function reinitialiserAvecCodeSecours(Request $request)
    {
        $rules = [
            'phone' => 'required|string',
            'code' => 'required|string',
            'nouveau' => 'required|string|min:8',
        ];

        $messages = [
            'phone.required' => 'Veuillez saisir votre numéro de téléphone.',
            'code.required' => 'Veuillez saisir un code de secours.',
            'nouveau.required' => 'Veuillez saisir votre nouveau mot de passe.',
            'nouveau.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $reussi = $this->recuperationCompteService->reinitialiserAvecCodeSecours(
            $request->phone,
            $request->code,
            $request->nouveau,
            $request,
        );

        if (!$reussi) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone ou code de secours invalide.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès',
        ]);
    }

    public function supprimerCompte(Request $request, $id)
    {
        if ((int) $id !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez supprimer que votre propre compte.",
            ], 403);
        }

        $utilisateur = UsersApp::find($id);

        if (!$utilisateur) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de supprimer votre compte. Veuillez recommencer.",
            ], 401);
        }

        try {
            (new SuppressionCompteService())->supprimer($utilisateur);
        } catch (SuppressionCompteBloqueeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Compte supprimé avec succès',
        ]);
    }
}
