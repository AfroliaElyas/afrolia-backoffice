<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\HairController;
use App\Http\Controllers\HelpsController;
use App\Http\Controllers\LancementController;
use App\Http\Controllers\LitigeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PolitiqueController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RecuperationCompteController;
use App\Http\Controllers\RemboursementController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\UtilisateursController;
use App\Http\Controllers\VersementController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('custom-login', [CustomAuthController::class, 'customLogin']);
Route::get('logout', [CustomAuthController::class, 'signOut'])->name('logout');

// Page de présentation publique d'Afrolia (pas d'authentification requise),
// sur le domaine public afrolia-app.com. La connexion back-office est passée
// sur /admin pour laisser la racine à cette page.
Route::get('/', function () {
    $languesDisponibles = ['fr', 'en'];
    $langueDemandee = request()->query('lang');

    if (in_array($langueDemandee, $languesDisponibles, true)) {
        session(['locale_site' => $langueDemandee]);
    }

    app()->setLocale(session('locale_site', 'fr'));

    return response()
        ->view('site.accueil')
        ->header('Cache-Control', 'private, no-store');
});

// Formulaire "être prévenue du lancement" sur la page d'accueil publique.
Route::post('lancement/inscription', [LancementController::class, 'inscrire'])
    ->middleware('throttle:10,1');

// Pages "À propos", "Politique de confidentialité" et "Conditions générales"
// publiques, même principe que la page d'accueil : jusqu'ici elles
// n'avaient pas de page dédiée sur le site (contrairement à l'app, qui les
// affiche déjà via l'API), le footer les annonçait donc comme "bientôt en
// ligne".
Route::get('a-propos', [AboutController::class, 'page']);
Route::get('politique', [PolitiqueController::class, 'page']);
Route::get('conditions', [TermsController::class, 'page']);

Route::get('/admin', function () {
    if (Auth::check()) {
        return redirect()->intended('index');
    }
    return view('auth.sign-in');
});

// Authentification
Route::get('sign-up', function () {
    return view('auth.sign-up');
});
Route::get('forgot', function () {
    return view('auth.forgot-password');
});

// Pages ouvertes depuis le lien reçu par e-mail (vérification d'adresse,
// réinitialisation de mot de passe) : consultées depuis un navigateur, pas
// depuis l'application mobile. Limitées en fréquence pour freiner les
// tentatives répétées de deviner un jeton.
Route::get('verifier-email/{token}', [RecuperationCompteController::class, 'verifierEmail'])
    ->middleware('throttle:20,1');
Route::get('reinitialiser-mot-de-passe/{token}', [RecuperationCompteController::class, 'formulaireReinitialisation'])
    ->middleware('throttle:20,1');
Route::post('reinitialiser-mot-de-passe/{token}', [RecuperationCompteController::class, 'traiterReinitialisation'])
    ->middleware('throttle:20,1');

// Tout le reste du back-office (gestion des comptes, finance, litiges,
// rapports, réglages...) exige une session admin authentifiée.
Route::middleware('auth')->group(function () {
    Route::get('index', [CustomAuthController::class, 'dashboard']);

    // utilisateurs
    Route::resource('users', UtilisateursController::class);
    Route::post('users/{id}/suspend', [UtilisateursController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{id}/reactivate', [UtilisateursController::class, 'reactivate'])->name('users.reactivate');

    Route::resource('hair', HairController::class);
    Route::post('hair/{id}/suspend', [HairController::class, 'suspend'])->name('hair.suspend');
    Route::post('hair/{id}/reactivate', [HairController::class, 'reactivate'])->name('hair.reactivate');

    Route::resource('evenement', ReservationController::class);
    Route::get('user-add', function () {
        return view('users.add-user');
    });
    Route::get('view-profile', function () {
        return view('users.view-profile');
    });
    //{{ url()->previous() }}
    // rapports
    Route::get('transaction', function () {
        return view('layouts.master');
    });
    Route::get('abonnement', function () {
        return view('layouts.master');
    });
    Route::get('utilisateur', function () {
        return view('layouts.master');
    });
    Route::get('pharmacies', function () {
        return view('layouts.master');
    });

    // Finance
    Route::resource('publicite', PaiementController::class);

    Route::resource('remboursement', RemboursementController::class);
    Route::post('remboursements/{id}/traiter', [RemboursementController::class, 'traiter'])->name('remboursements.traiter');
    Route::post('remboursements/{id}/rejeter', [RemboursementController::class, 'rejeter'])->name('remboursements.rejeter');
    Route::post('remboursements/{id}/confirmer-verification', [RemboursementController::class, 'confirmerVerification'])->name('remboursements.confirmerVerification');

    Route::get('versements', [VersementController::class, 'index'])->name('versements');
    Route::post('versements/coiffeuse/{idCoiffeur}/infos', [VersementController::class, 'definirInfosVersement'])->name('versements.infos');
    Route::post('versements/preparer/{idCoiffeur}', [VersementController::class, 'preparer'])->name('versements.preparer');
    Route::post('versements/{id}/confirmer', [VersementController::class, 'confirmer'])->name('versements.confirmer');
    Route::post('versements/{id}/annuler', [VersementController::class, 'annuler'])->name('versements.annuler');

    // Tarifs
    Route::get('tarifs', [TarifController::class, 'index'])->name('tarifs');
    Route::post('tarifs', [TarifController::class, 'update'])->name('tarifs.update');

    // Rapports
    Route::get('rapport-financier', [RapportController::class, 'financier'])->name('rapport-financier');
    Route::get('stats-utilisateurs', [RapportController::class, 'utilisateurs'])->name('stats-utilisateurs');
    Route::get('performances-reservations', [RapportController::class, 'reservations'])->name('performances-reservations');
    Route::get('satisfaction-client', [RapportController::class, 'satisfaction'])->name('satisfaction-client');

    // Litiges
    Route::get('litiges', [LitigeController::class, 'index'])->name('litiges');
    Route::post('litiges/{id}/resoudre', [LitigeController::class, 'resoudre'])->name('litiges.resoudre');
    Route::post('litiges/{id}/rejeter', [LitigeController::class, 'rejeter'])->name('litiges.rejeter');
    Route::post('litiges/{id}/en-cours', [LitigeController::class, 'enCours'])->name('litiges.en-cours');

    // termes
    Route::resource('terms-condition', TermsController::class);
    Route::resource('terms-politique', PolitiqueController::class);
    Route::resource('terms-about', AboutController::class);
    Route::post('/admin/termes-conditions', [TermsController::class, 'enregistrerOuMettreAJour'])->name('termes.storeOrUpdate');
    Route::post('/admin/termes-politique', [PolitiqueController::class, 'enregistrerOuMettreAJour'])->name('termes.storeOrPolitique');
    Route::post('/admin/termes-about', [AboutController::class, 'enregistrerOuMettreAJour'])->name('termes.storeOrAbout');
    Route::resource('terms-helps', HelpsController::class);
    Route::post('/admin/termes-helps', [HelpsController::class, 'enregistrerOuMettreAJour'])->name('termes.storeOrHelps');

    // setting
    Route::get('notification', function () {
        return view('layouts.master');
    });
    Route::get('notification-alert', function () {
        return view('layouts.master');
    });
    Route::get('payment-gateway', function () {
        return view('layouts.master');
    });
    Route::get('siteweb', function () {
        return view('welcome');
    });
    Route::resource('company', CompanyController::class);
});
