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

class UtilisateursController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $users = UsersApp::where('role', 'user')
            ->withCount([
                'reservationsClient as reservations_count',
            ])
            ->withSum([
                'reservationsClient as total_depenses' => function ($query) {
                    $query->where('statut_paiement', 'paye');
                },
            ], 'montant_total')
            ->latest()
            ->get();

        return view('users.users-list', compact('users'));
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
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:100',
            'telephone' => 'required|string|max:100|unique:users_app,phone',
            'email'     => 'nullable|email|max:100|unique:users_app,email',
            'commune'   => 'nullable|string|max:100',
            'photo'     => 'nullable|image|max:2048',
        ];

        $customMessages = [
            'nom.required'       => 'Veuillez saisir le nom du client.',
            'prenom.required'    => 'Veuillez saisir le prénom du client.',
            'telephone.required' => 'Veuillez saisir le téléphone du client.',
            'telephone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'        => "L'adresse e-mail n'est pas valide.",
            'email.unique'       => 'Cette adresse e-mail est déjà utilisée.',
        ];

        $request->validate($rules, $customMessages);

        $user = new UsersApp();
        $user->last_name = $request->nom;
        $user->name       = $request->prenom;
        $user->phone      = $request->telephone;
        $user->email      = $request->email;
        $user->commune    = $request->commune;
        $user->role       = 'user';
        $user->statut     = 'Active';
        // Mot de passe temporaire — à faire choisir/réinitialiser par le client via OTP
        $user->password   = Hash::make(str()->random(12));

        if ($request->hasFile('photo')) {
            $user->photo = $request->file('photo')->store('users', 'public');
        }

        if ($user->save()) {
            return back()->with('succes', "Vous avez ajouté {$request->prenom} {$request->nom}.");
        }

        return back()->withErrors(["Impossible d'ajouter {$request->prenom} {$request->nom}. Veuillez réessayer."]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = UsersApp::where('role', 'user')
            ->withCount(['reservationsClient as reservations_count'])
            ->withSum([
                'reservationsClient as total_depenses' => function ($query) {
                    $query->where('statut_paiement', 'paye');
                },
            ], 'montant_total')
            ->findOrFail($id);

        $reservations = DB::table('reservations')
            ->join('users_app as coiffeurs', 'reservations.id_coiffeur', '=', 'coiffeurs.id_user_app')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->where('reservations.id_client', $id)
            ->select(
                'reservations.*',
                'coiffeurs.name as coiffeur_prenom',
                'coiffeurs.last_name as coiffeur_nom',
                'specialites.libelle as service_libelle',
                'services.prix as service_prix',
                'services.minute as service_duree'
            )
            ->orderByDesc('reservations.date_reservation')
            ->orderByDesc('reservations.heure_reservation')
            ->get();

        $reviews = DB::table('reviews')
            ->join('users_app as stylists', 'reviews.id_stylist', '=', 'stylists.id_user_app')
            ->where('reviews.id_client', $id)
            ->select('reviews.*', 'stylists.name as stylist_prenom', 'stylists.last_name as stylist_nom')
            ->orderByDesc('reviews.created_at')
            ->get();

        $favoritesCount = DB::table('client_favorites')
            ->where('client_id', $id)
            ->count();

        $avgRatingGiven = DB::table('reviews')
            ->where('id_client', $id)
            ->avg('rating');

        return view('users.view-profile', compact(
            'user',
            'reservations',
            'reviews',
            'favoritesCount',
            'avgRatingGiven'
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
        $user = UsersApp::where('role', 'user')->findOrFail($id);

        $rules = [
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:100',
            'telephone' => 'required|string|max:100|unique:users_app,phone,' . $id . ',id_user_app',
            'email'     => 'nullable|email|max:100|unique:users_app,email,' . $id . ',id_user_app',
            'commune'   => 'nullable|string|max:100',
            'photo'     => 'nullable|image|max:2048',
        ];

        $customMessages = [
            'nom.required'       => 'Veuillez saisir le nom du client.',
            'prenom.required'    => 'Veuillez saisir le prénom du client.',
            'telephone.required' => 'Veuillez saisir le téléphone du client.',
            'telephone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'        => "L'adresse e-mail n'est pas valide.",
            'email.unique'       => 'Cette adresse e-mail est déjà utilisée.',
        ];

        $request->validate($rules, $customMessages);

        $user->last_name = $request->nom;
        $user->name       = $request->prenom;
        $user->phone      = $request->telephone;
        $user->email      = $request->email;
        $user->commune    = $request->commune;

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $user->photo = $request->file('photo')->store('users', 'public');
        }

        if ($user->save()) {
            return back()->with('succes', 'Le client a été modifié avec succès.');
        }

        return back()->withErrors(['Problème lors de la modification. Veuillez réessayer.']);
    }

    /**
     * Suspendre un client.
     */
    public function suspend(Request $request, string $id)
    {
        $request->validate([
            'raison' => 'required|string|max:500',
        ], [
            'raison.required' => 'Veuillez indiquer la raison de la suspension.',
        ]);

        $user = UsersApp::where('role', 'user')->findOrFail($id);
        $user->statut = 'Inactive';
        $user->raison_suspension = $request->raison;
        $user->save();

        return back()->with('succes', 'Le client a été suspendu.');
    }

    /**
     * Réactiver un client.
     */
    public function reactivate(string $id)
    {
        $user = UsersApp::where('role', 'user')->findOrFail($id);
        $user->statut = 'Active';
        $user->raison_suspension = null;
        $user->save();

        return back()->with('succes', 'Le client a été réactivé.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = UsersApp::where('role', 'user')->findOrFail($id);

        try {
            (new SuppressionCompteService())->supprimer($user);
        } catch (SuppressionCompteBloqueeException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return back()->with('succes', 'La suppression a été effectuée.');
    }
}
