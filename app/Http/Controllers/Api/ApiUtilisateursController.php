<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersApp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApiUtilisateursController extends Controller
{
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
            'email' => 'nullable|string|unique:users_app,email',
            'password' => 'required',
            'role' => 'required',
        ];

        $messages = [
            'nom.required' => 'Veuillez saisir votre nom.',
            'prenom.required' => 'Veuillez saisir votre prénom.',
            'phone.required' => 'Veuillez saisir votre numéro de téléphone.',
            'phone.unique' => 'Le numéro de téléphone est deja utilisé.',
            'email.uninque' => "L'adresse email est deja utilisé.",
            'password.required' => 'Veuillez saisir votre mot de passe.',
            'role.required' => 'Veuillez selectionner votre type de conpte.',
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

            return response()->json([
                'success' => true,
                'message' => 'Vous êtes inscrit avec succès',
                'data' => [
                    'id' => $utilisateur->id_user_app,
                    'role' => $utilisateur->role,
                    'token' => $token,
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
            if ($utilisateur->photo_utilisateur) {
                $anciennePhotoPath = str_replace('/storage/', '', $utilisateur->photo_utilisateur);
                Storage::disk('public')->delete($anciennePhotoPath);
            }

            $timestamp = Carbon::now()->format('Ymd_His');
            $photo = $request->file('photo');
            $photoName = 'photo_' . $timestamp . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('utilisateurs/photos', $photoName, 'public');
            $utilisateur->photo_utilisateur = Storage::url($photoPath);
        }

        // Mise à jour des champs modifiables
        if ($request->filled('nom')) {
            $utilisateur->nom_utilisateur = $request->nom;
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
                'photo' => url($utilisateur->photo_utilisateur)
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Impossible de mettre à jour vos informations. Veuillez recommencer.",
            ], 401);
        }
    }

    public function demanderOtpReset(Request $request)
    {
        $rules = [
            'phone' => 'required|exists:users_app,phone',
        ];

        $messages = [
            'phone.exists' => 'Le numéro de téléphone n\'est pas enregistré.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur = UsersApp::where('phone', $request->phone)->first();

        // Générer un OTP aléatoire
        $otp = rand(100000, 999999);
        $utilisateur->otp = $otp;
        $utilisateur->save();

        // TODO : Envoyer le code OTP via SMS
        // Exemple de log
        Log::info("OTP pour {$utilisateur->phone} : $otp");

        return response()->json([
            'success' => true,
            'message' => 'Code OTP envoyé avec succès',
        ]);
    }

    public function resetPasswordWithOtp(Request $request)
    {

        $rules = [
            'phone' => 'required|exists:users_app,phone',
            'otp' => 'required',
            'nouveau' => 'required',
        ];

        $messages = [
            'phone.required' => 'Veuillez saisir votre numéro de téléphone.',
            'phone.exists' => 'Le numéro de téléphone n\'est pas enregistré.',
            'nouveau.required' => 'Veuillez saisir votre nouveau mot de passe.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $utilisateur = UsersApp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->first();

        if (!$utilisateur) {
            return response()->json([
                'success' => false,
                'message' => 'OTP invalide ou expiré',
            ], 401);
        }

        $utilisateur->password = Hash::make($request->nouveau);
        $utilisateur->otp = null; // on réinitialise l’OTP

        if ($utilisateur->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Impossible de réinitialiser votre mot de passe. Veuillez recommencer.",
            ], 401);
        }
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

        // Supprimer les fichiers liés (photo, recto, verso)
        $fichiers = ['photo'];

        foreach ($fichiers as $champ) {
            if ($utilisateur->$champ) {
                // Extrait juste le nom du fichier depuis le champ (ex: verso_20250614_011132.jpg)
                $nomFichier = basename($utilisateur->$champ);

                // Déduit le sous-dossier depuis le champ
                $dossier = '';
                if ($champ === 'photo') {
                    $dossier = 'images/utilisateurs/photos/';
                }

                // Construit le chemin absolu complet
                $cheminComplet = public_path($dossier . $nomFichier);

                // Supprime le fichier s’il existe
                if (file_exists($cheminComplet)) {
                    unlink($cheminComplet);
                }
            }
        }

        $utilisateur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Compte supprimé avec succès',
        ]);
    }
}
