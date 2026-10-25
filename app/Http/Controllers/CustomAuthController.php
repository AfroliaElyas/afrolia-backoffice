<?php

namespace App\Http\Controllers;

use App\Models\Gains;
use App\Models\Reservations;
use App\Models\User;
use App\Models\UsersApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CustomAuthController extends Controller
{
    /**
     * 5 essais par minute, par couple e-mail + IP : assez pour une vraie
     * erreur de frappe, trop peu pour un essai systématique de mots de
     * passe (faille relevée par Emergent AI le 25/10/2026 — aucune limite
     * n'existait avant). Combiner e-mail et IP évite qu'un visiteur sur la
     * même IP (réseau partagé) ne bloque les autres.
     */
    private const MAX_TENTATIVES_CONNEXION = 5;
    private const DUREE_BLOCAGE_SECONDES = 60;

    public function index()
    {
        return view('auth.sign-in');
    }

    public function customLogin(Request $request)
    {
        $roles = [
            'email' => 'required',
            'password' => 'required',
        ];
        $customMessages = [
            'email.required' => "Veuillez saisir votre adresse email.",
            'password.required' => "Veuillez saisir votre mot de passe.",
        ];

        $request->validate($roles, $customMessages);

        $cleLimitation = Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($cleLimitation, self::MAX_TENTATIVES_CONNEXION)) {
            $secondesRestantes = RateLimiter::availableIn($cleLimitation);

            return back()->withErrors([
                "Trop de tentatives de connexion. Réessayez dans {$secondesRestantes} secondes.",
            ]);
        }

        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            RateLimiter::clear($cleLimitation);
            Auth::login($user);
            return redirect()->intended('index');
        } else {
            RateLimiter::hit($cleLimitation, self::DUREE_BLOCAGE_SECONDES);
            return back()->withErrors(['E-mail ou mot de passe incorrect.']);
        }
    }

    public function dashboard()
    {
        if (!Auth::check()) {
            return view('auth.sign-in');
        }

        $client = UsersApp::where('role', 'user')->count();
        $hair   = UsersApp::where('role', 'hair')->count();

        $reservationTerminee = Reservations::where('statut', 'terminee')->count();
        $reservationEncours  = Reservations::where('statut', 'en_attente')->count();

        $total      = Gains::sum('montant_brut');
        $commission = Gains::sum('montant_commission');
        $coiffeuse  = Gains::sum('montant_net');
        $attente    = Gains::where('statut', 'en_attente')->sum('montant_net');

        // ── Revenus mensuels (12 derniers mois) pour le graphique #chart ──
        $monthlyRaw = DB::table('gains')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key, SUM(montant_brut) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->pluck('total', 'month_key');

        $monthLabels = [];
        $monthValues = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key  = $date->format('Y-m');
            $monthLabels[] = ucfirst($date->translatedFormat('M'));
            $monthValues[] = (float) ($monthlyRaw[$key] ?? 0);
        }

        // ── Répartition des gains par statut pour #paymentStatusChart ──
        $gainsParStatut = DB::table('gains')
            ->selectRaw('statut, SUM(montant_net) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $paymentStatusLabels = ['En attente', 'Disponible', 'Payé'];
        $paymentStatusValues = [
            (float) ($gainsParStatut['en_attente'] ?? 0),
            (float) ($gainsParStatut['disponible'] ?? 0),
            (float) ($gainsParStatut['paye'] ?? 0),
        ];

        // ── Dernières réservations ──
        $recentReservations = DB::table('reservations')
            ->join('users_app as clients', 'reservations.id_client', '=', 'clients.id_user_app')
            ->join('services', 'reservations.id_service', '=', 'services.id_service')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->select(
                'reservations.*',
                'clients.name as client_prenom',
                'clients.last_name as client_nom',
                'clients.email as client_email',
                'clients.photo as client_photo',
                'specialites.libelle as service_libelle'
            )
            ->orderByDesc('reservations.created_at')
            ->limit(5)
            ->get();

        return view('home.index', compact(
            'client',
            'hair',
            'reservationTerminee',
            'reservationEncours',
            'total',
            'commission',
            'coiffeuse',
            'attente',
            'monthLabels',
            'monthValues',
            'paymentStatusLabels',
            'paymentStatusValues',
            'recentReservations'
        ));
    }

    public function signOut()
    {
        Session::flush();
        Auth::logout();
        return Redirect('/admin');
    }
}
