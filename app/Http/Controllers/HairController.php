<?php

namespace App\Http\Controllers;

use App\Models\UsersApp;
use App\Services\SuppressionCompteBloqueeException;
use App\Services\SuppressionCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class HairController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $users = UsersApp::where('role', 'hair')
            ->withCount([
                'reservationsCoiffeur as reservations_count' => function ($query) {
                    $query->where('statut', 'terminee');
                },
            ])
            ->withSum([
                'reservationsCoiffeur as revenus_total' => function ($query) {
                    $query->where('statut', 'terminee');
                },
            ], 'prix_service')
            ->latest()
            ->get();

        return view('users.hair-list', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'nom'          => 'required|string|max:255',
            'prenom'       => 'required|string|max:100',
            'telephone'    => 'required|string|max:100|unique:users_app,phone',
            'email'        => 'nullable|email|max:100|unique:users_app,email',
            'commune'      => 'nullable|string|max:100',
            'adresse'      => 'nullable|string|max:255',
            'experience'   => 'nullable|integer|min:0',
            'presentation' => 'nullable|string|max:500',
            'photo'        => 'nullable|image|max:2048',
        ];

        $customMessages = [
            'nom.required'       => 'Veuillez saisir le nom de la coiffeuse.',
            'prenom.required'    => 'Veuillez saisir le prénom de la coiffeuse.',
            'telephone.required' => 'Veuillez saisir le téléphone de la coiffeuse.',
            'telephone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'        => "L'adresse e-mail n'est pas valide.",
            'email.unique'       => 'Cette adresse e-mail est déjà utilisée.',
        ];

        $request->validate($rules, $customMessages);

        $hair = new UsersApp();
        $hair->last_name     = $request->nom;
        $hair->name           = $request->prenom;
        $hair->phone          = $request->telephone;
        $hair->email          = $request->email;
        $hair->commune        = $request->commune;
        $hair->adresse        = $request->adresse;
        $hair->experience     = $request->experience;
        $hair->presentation   = $request->presentation;
        $hair->role           = 'hair';
        $hair->statut         = 'Active';
        $hair->password       = Hash::make(str()->random(12));

        if ($request->hasFile('photo')) {
            $hair->photo = $request->file('photo')->store('users', 'public');
        }

        if ($hair->save()) {
            return back()->with('succes', "Vous avez ajouté {$request->prenom} {$request->nom}.");
        }

        return back()->withErrors(["Impossible d'ajouter {$request->prenom} {$request->nom}. Veuillez réessayer."]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $hair = UsersApp::where('role', 'hair')
            ->withCount([
                'reservationsCoiffeur as reservations_count' => function ($query) {
                    $query->where('statut', 'terminee');
                },
            ])
            ->findOrFail($id);

        $reservations = DB::table('reservations')
            ->join('users_app as clients', 'reservations.id_client', '=', 'clients.id_user_app')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->where('reservations.id_coiffeur', $id)
            ->select(
                'reservations.*',
                'clients.name as client_prenom',
                'clients.last_name as client_nom',
                'specialites.libelle as service_libelle'
            )
            ->orderByDesc('reservations.date_reservation')
            ->orderByDesc('reservations.heure_reservation')
            ->get();

        $reviews = DB::table('reviews')
            ->join('users_app as clients', 'reviews.id_client', '=', 'clients.id_user_app')
            ->where('reviews.id_stylist', $id)
            ->where('reviews.status', 'approved')
            ->select('reviews.*', 'clients.name as client_prenom', 'clients.last_name as client_nom')
            ->orderByDesc('reviews.created_at')
            ->get();

        $avgRatingReceived = DB::table('reviews')
            ->where('id_stylist', $id)
            ->where('status', 'approved')
            ->avg('rating');

        $gains = DB::table('gains')->where('id_coiffeur', $id)->get();
        $gainsParStatut = $gains->groupBy('statut')->map(fn($g) => $g->sum('montant_net'));

        $services = DB::table('services')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->where('services.id_utilisateur', $id)
            ->select('services.*', 'specialites.libelle as specialite_libelle')
            ->get();

        $specialites = DB::table('users_app_specialites')
            ->join('specialites', 'users_app_specialites.id_speciale', '=', 'specialites.id_specialite')
            ->where('users_app_specialites.id_utilisateur', $id)
            ->pluck('specialites.libelle');

        $langues = DB::table('users_app_langues_parlees')
            ->join('langues', 'users_app_langues_parlees.id_language', '=', 'langues.id_langue')
            ->where('users_app_langues_parlees.id_utilisateur', $id)
            ->pluck('langues.libelle'); // adapte le nom de colonne si différent

        $methodesPaiement = DB::table('users_app_methode_paiement')
            ->join('methode_paiement', 'users_app_methode_paiement.id_methode', '=', 'methode_paiement.id_methode_paiement')
            ->where('users_app_methode_paiement.id_utilisateur', $id)
            ->pluck('methode_paiement.libelle'); // adapte le nom de colonne si différent

        $disponibilites = DB::table('disponibilites')
            ->join('jours', 'disponibilites.id_day', '=', 'jours.id_jour')
            ->join('heures', 'disponibilites.id_time', '=', 'heures.id_heure')
            ->where('disponibilites.id_utilisateur', $id)
            ->select('jours.libelle as jour_libelle', 'heures.libelle as heure_libelle') // adapte les colonnes
            ->orderBy('jours.id_jour')
            ->orderBy('heures.id_heure')
            ->get()
            ->groupBy('jour_libelle');

        $sociaux = DB::table('sociaux')->where('id_utilisateur', $id)->first();

        $gallery = DB::table('gallery')->where('id_utilisateur', $id)->orderByDesc('created_at')->get();

        return view('users.hair-detail', compact(
            'hair',
            'reservations',
            'reviews',
            'avgRatingReceived',
            'gainsParStatut',
            'services',
            'specialites',
            'langues',
            'methodesPaiement',
            'disponibilites',
            'sociaux',
            'gallery'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $hair = UsersApp::where('role', 'hair')->findOrFail($id);

        $rules = [
            'nom'          => 'required|string|max:255',
            'prenom'       => 'required|string|max:100',
            'telephone'    => 'required|string|max:100|unique:users_app,phone,' . $id . ',id_user_app',
            'email'        => 'nullable|email|max:100|unique:users_app,email,' . $id . ',id_user_app',
            'commune'      => 'nullable|string|max:100',
            'adresse'      => 'nullable|string|max:255',
            'experience'   => 'nullable|integer|min:0',
            'presentation' => 'nullable|string|max:500',
            'photo'        => 'nullable|image|max:2048',
        ];

        $customMessages = [
            'nom.required'       => 'Veuillez saisir le nom de la coiffeuse.',
            'prenom.required'    => 'Veuillez saisir le prénom de la coiffeuse.',
            'telephone.required' => 'Veuillez saisir le téléphone de la coiffeuse.',
            'telephone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'        => "L'adresse e-mail n'est pas valide.",
            'email.unique'       => 'Cette adresse e-mail est déjà utilisée.',
        ];

        $request->validate($rules, $customMessages);

        $hair->last_name     = $request->nom;
        $hair->name           = $request->prenom;
        $hair->phone          = $request->telephone;
        $hair->email          = $request->email;
        $hair->commune        = $request->commune;
        $hair->adresse        = $request->adresse;
        $hair->experience     = $request->experience;
        $hair->presentation   = $request->presentation;

        if ($request->hasFile('photo')) {
            if ($hair->photo) {
                Storage::disk('public')->delete($hair->photo);
            }
            $hair->photo = $request->file('photo')->store('users', 'public');
        }

        if ($hair->save()) {
            return back()->with('succes', 'La coiffeuse a été modifiée avec succès.');
        }

        return back()->withErrors(['Problème lors de la modification. Veuillez réessayer.']);
    }

    /**
     * Suspendre une coiffeuse.
     */
    public function suspend(Request $request, string $id)
    {
        $request->validate([
            'raison' => 'required|string|max:500',
        ], [
            'raison.required' => 'Veuillez indiquer la raison de la suspension.',
        ]);

        $hair = UsersApp::where('role', 'hair')->findOrFail($id);
        $hair->statut = 'Inactive';
        $hair->raison_suspension = $request->raison;
        $hair->save();

        return back()->with('succes', 'La coiffeuse a été suspendue.');
    }

    /**
     * Réactiver une coiffeuse.
     */
    public function reactivate(string $id)
    {
        $hair = UsersApp::where('role', 'hair')->findOrFail($id);
        $hair->statut = 'Active';
        $hair->raison_suspension = null;
        $hair->save();

        return back()->with('succes', 'La coiffeuse a été réactivée.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $hair = UsersApp::where('role', 'hair')->findOrFail($id);

        try {
            (new SuppressionCompteService())->supprimer($hair);
        } catch (SuppressionCompteBloqueeException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return back()->with('succes', 'La suppression a été effectuée.');
    }
}
