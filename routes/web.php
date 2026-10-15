<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConfigTarifController;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\HairController;
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

    return view('site.accueil');
});

// Formulaire "être prévenue du lancement" sur la page d'accueil publique.
Route::post('lancement/inscription', [LancementController::class, 'inscrire'])
    ->middleware('throttle:10,1');

// Route temporaire de diagnostic pour vérifier l'envoi d'e-mails via le
// fournisseur actif (Brevo/Resend). À retirer une fois Resend confirmé
// fonctionnel. Protégée par un jeton pour éviter un envoi déclenché par
// n'importe qui tombant sur l'URL.
Route::get('test-envoi-email/9r5zSyiG78ulCk0bYUf_vn91ut8dTnte', function () {
    $diagnostic = [
        'mailer' => config('mail.default'),
        'from' => config('mail.from.address'),
        'resend_key_presente' => !empty(config('services.resend.key')),
        'brevo_key_presente' => !empty(config('services.brevo.key')),
    ];

    try {
        \Illuminate\Support\Facades\Mail::raw('Ceci est un test reel d\'envoi depuis Afrolia.', function ($message) {
            $message->to('afroliaofficiel@gmail.com')->subject('Test envoi Afrolia');
        });
        $diagnostic['resultat'] = 'succes';
    } catch (\Throwable $e) {
        $diagnostic['resultat'] = 'echec';
        $diagnostic['erreur_classe'] = get_class($e);
        $diagnostic['erreur_message'] = $e->getMessage();
    }

    return response()->json($diagnostic, 200, [], JSON_PRETTY_PRINT);
})->middleware('throttle:5,1');

// Route temporaire de diagnostic : indique seulement combien de comptes
// administrateur existent au total, et si une adresse précise (passée en
// paramètre) correspond à l'un d'eux — jamais la liste des e-mails, ni le
// mot de passe. À retirer une fois le compte admin confirmé fonctionnel.
// Protégée par un jeton.
Route::get('verifier-compte-admin/mGXrIrY5n2cLLVfBeOGTW1aIhweWlHKA', function () {
    $emailTeste = request()->query('email');

    $reponse = [
        'nombre_total_de_comptes' => \App\Models\User::count(),
        'email_teste' => $emailTeste,
    ];

    if ($emailTeste) {
        $reponse['ce_compte_existe'] = \App\Models\User::where('email', $emailTeste)->exists();
    }

    return response()->json($reponse, 200, [], JSON_PRETTY_PRINT);
})->middleware('throttle:5,1');

// Route temporaire : un vrai petit formulaire pour créer le tout premier
// compte administrateur. Le mot de passe est choisi et tapé directement
// par la personne dans son navigateur — il n'est jamais généré, affiché
// ni renvoyé par le serveur. Ne touche à rien si un compte existe déjà
// avec cet e-mail, pour ne jamais en écraser un. À retirer une fois le
// compte créé.
Route::get('creer-compte-admin/YvvKORAIKWjEJr5X9qs1F1MHp1YzIynf', function () {
    $emailExistant = \App\Models\User::where('email', 'afroliaofficiel@gmail.com')->exists();

    return response(view('admin-bootstrap.formulaire', ['emailExistant' => $emailExistant]));
})->middleware('throttle:5,1');

Route::post('creer-compte-admin/YvvKORAIKWjEJr5X9qs1F1MHp1YzIynf', function (\Illuminate\Http\Request $request) {
    $email = 'afroliaofficiel@gmail.com';

    if (\App\Models\User::where('email', $email)->exists()) {
        return back()->withErrors(['Un compte existe déjà avec cet e-mail, aucune modification effectuée.']);
    }

    $request->validate([
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    \App\Models\User::create([
        'name' => 'Afrolia',
        'email' => $email,
        'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
    ]);

    return view('admin-bootstrap.succes', ['email' => $email]);
})->middleware('throttle:5,1');

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
