<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConfigTarifController;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\HairController;
use App\Http\Controllers\LitigeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PolitiqueController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RemboursementController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\UtilisateursController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Page affichée au client quand il revient de la page de paiement Jèko.
// Elle ne prouve rien : le paiement n'est confirmé que par le webhook.
Route::get('/paiement/retour', function (\Illuminate\Http\Request $request) {
    $ok = $request->query('statut') === 'success';

    return response(
        '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Afrolia</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:48px 24px">'
        . '<h2>' . ($ok ? 'Merci, paiement reçu' : 'Paiement non abouti') . '</h2>'
        . '<p>' . ($ok
            ? 'Retournez dans l\'application Afrolia : la confirmation s\'affichera dans quelques secondes.'
            : 'Retournez dans l\'application Afrolia pour réessayer.') . '</p>'
        . '</body></html>'
    );
});

Route::post('custom-login', [CustomAuthController::class, 'customLogin']);
Route::get('logout', [CustomAuthController::class, 'signOut'])->name('logout');

Route::get('/', function () {
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

// Tout le reste du back-office (gestion des comptes, finance, litiges,
// rapports, réglages...) exige une session admin authentifiée.
Route::middleware('auth')->group(function () {
    Route::get('index', [CustomAuthController::class, 'dashboard']);

    // utilisateurs
    Route::resource('users', UtilisateursController::class);
    Route::post('users/{id}/suspend', [UtilisateursController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{id}/reactivate', [UtilisateursController::class, 'reactivate'])->name('users.reactivate');

    Route::resource('hair', HairController::class);
    // Route::get('hair', [HairController::class, 'index'])->name('hair.index');
    Route::post('hair', [HairController::class, 'store'])->name('hair.store');
    Route::get('hair/{id}', [HairController::class, 'show'])->name('hair.show');
    Route::put('hair/{id}', [HairController::class, 'update'])->name('hair.update');
    Route::delete('hair/{id}', [HairController::class, 'destroy'])->name('hair.destroy');
    Route::post('hair/{id}/suspend', [HairController::class, 'suspend'])->name('hair.suspend');
    Route::post('hair/{id}/reactivate', [HairController::class, 'reactivate'])->name('hair.reactivate');

    Route::resource('evenement', ReservationController::class);
    Route::get('user-add', function () {
        return view('users.add-user');
    });
    Route::get('view-profile', function () {
        return view('users.view-profile');
    });
    Route::get('conditions', function () {
        return view('conditions.condition');
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
