<?php

use App\Http\Controllers\Api\ApiAbonnementController;
use App\Http\Controllers\Api\ApiAvisController;
use App\Http\Controllers\Api\ApiClientFavoriteController;
use App\Http\Controllers\Api\ApiCommandesController;
use App\Http\Controllers\Api\ApiDashboardCoiffeuseController;
use App\Http\Controllers\Api\ApiGainsCoiffeuseController;
use App\Http\Controllers\Api\ApiPaiementsController;
use App\Http\Controllers\Api\ApiProduitsController;
use App\Http\Controllers\Api\ApiReservationsController;
use App\Http\Controllers\Api\ApiSalonController;
use App\Http\Controllers\Api\ApiSecurityController;
use App\Http\Controllers\Api\ApiServicesController;
use App\Http\Controllers\Api\ApiSociauxController;
use App\Http\Controllers\Api\ApiUserSalonController;
use App\Http\Controllers\Api\ApiUtilisateursController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
| Authentification, contenu de référence (spécialités, langues, jours/heures),
| et contenu vitrine consultable avant connexion (profils, catalogue, avis) :
| aucune de ces routes ne doit exposer de donnée personnelle.
*/

// Authentification — limitées en fréquence pour freiner les tentatives
// répétées de mot de passe ou de code OTP.
Route::post('login', [ApiUtilisateursController::class, 'login'])
    ->middleware('throttle:5,1');
Route::post('register', [ApiUtilisateursController::class, 'register']);
Route::post('otp', [ApiUtilisateursController::class, 'demanderOtpReset'])
    ->middleware('throttle:5,1');
Route::post('reset', [ApiUtilisateursController::class, 'resetPasswordWithOtp'])
    ->middleware('throttle:5,1');

// Confidentialité
Route::get('help', [ApiSecurityController::class, 'helps']);
Route::get('security', [ApiSecurityController::class, 'security']);

// Contenu de référence
Route::get('specialites', [ApiUserSalonController::class, 'specialites']);
Route::get('langues', [ApiUserSalonController::class, 'langues']);
Route::get('jours-heures', [ApiUserSalonController::class, 'getJoursEtHeures']);

// Vitrine publique : profils, catalogue et avis consultables avant connexion.
Route::get('presentation/{id}', [ApiUserSalonController::class, 'presentation']);
Route::get('associationspecialite/{id}', [ApiUserSalonController::class, 'assoSpecialite']);
Route::get('associationlangue/{id}', [ApiUserSalonController::class, 'assoLangue']);
Route::get('disponibilites/{id}', [ApiUserSalonController::class, 'getDisponibilitesByUser']);
Route::get('hair/services/{id}', [ApiServicesController::class, 'getServicesByUser']);
Route::get('hair/sociaux/{id}', [ApiSociauxController::class, 'getSociauxByUser']);
Route::get('hair/gallery/{id}', [ApiSociauxController::class, 'getGalleryByUser']);
Route::get('salons', [ApiSalonController::class, 'getCoiffeursAvecStatut']);
Route::get('salons/{id_coiffeur}', [ApiSalonController::class, 'getProfilCoiffeur']);
Route::get('produits', [ApiProduitsController::class, 'getCatalogue']);
Route::get('produits/coiffeuse/{id_coiffeur}', [ApiProduitsController::class, 'getProduitsByCoiffeuse']);
Route::get('avis/coiffeuse/{id_coiffeuse}', [ApiAvisController::class, 'getAvisByCoiffeuse']);

// Webhooks : appelés par Stripe / l'agrégateur mobile money, jamais par
// l'app — authentifiés par vérification de signature, pas par jeton.
Route::post('/stripe/webhook', [ApiPaiementsController::class, 'stripeWebhook']);
Route::post('/mobile-money/webhook', [ApiPaiementsController::class, 'mobileMoneyWebhook']);

/*
|--------------------------------------------------------------------------
| Routes protégées
|--------------------------------------------------------------------------
| Tout ce qui touche à un compte ou à ses données personnelles exige un
| jeton (Authorization: Bearer <token>, obtenu via /login ou /register).
| Chaque contrôleur vérifie en plus que l'action ne porte que sur les
| données de l'utilisateur authentifié (voir les contrôleurs Api/*).
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('logout', [ApiUtilisateursController::class, 'logout']);

    Route::post('update/{id}', [ApiUtilisateursController::class, 'update']);
    Route::delete('delete/{id}', [ApiUtilisateursController::class, 'supprimerCompte']);
    Route::get('delete/{id}', [ApiUtilisateursController::class, 'supprimerCompte']);

    Route::post('updateinfobasic/{id}', [ApiUserSalonController::class, 'updateInfoBasic']);
    Route::post('updatepresentation/{id}', [ApiUserSalonController::class, 'updatePresentation']);
    Route::post('hair/specialites/associer', [ApiUserSalonController::class, 'associerSpecialitesUtilisateur']);
    Route::post('hair/langues/associer', [ApiUserSalonController::class, 'associerLanguesUtilisateur']);
    Route::post('disponibilites', [ApiUserSalonController::class, 'saveDisponibilites']);
    Route::post('hair/services', [ApiServicesController::class, 'createServices']);
    Route::put('services/{id}', [ApiServicesController::class, 'updateService']);
    Route::delete('services/{id}', [ApiServicesController::class, 'deleteService']);
    Route::post('hair/sociaux', [ApiSociauxController::class, 'saveSociaux']);
    Route::post('hair/gallery', [ApiSociauxController::class, 'createGallery']);
    Route::put('gallery/{id}', [ApiSociauxController::class, 'updateGallery']);
    Route::delete('gallery/{id}', [ApiSociauxController::class, 'deleteGallery']);

    Route::prefix('reservations')->group(function () {
        Route::get('/user/{id}', [ApiReservationsController::class, 'getReservationByUser']);
        Route::post('/', [ApiReservationsController::class, 'store']);
        Route::put('/{id}', [ApiReservationsController::class, 'update']);
        Route::delete('/{id}', [ApiReservationsController::class, 'destroy']);

        Route::get('/coiffeuse/{id_coiffeuse}', [ApiReservationsController::class, 'getReservationsByCoiffeuse']);
        Route::put('/confirmer/{id_reservation}', [ApiReservationsController::class, 'confirmerReservation']);
        Route::put('/refuser/{id_reservation}', [ApiReservationsController::class, 'refuserReservation']);
        Route::put('/terminer/{id_reservation}', [ApiReservationsController::class, 'terminerReservation']);

        Route::get('/recentes/{id_coiffeur}', [ApiReservationsController::class, 'recentReservations']);
        Route::get('/statistiques/{id_coiffeur}', [ApiReservationsController::class, 'reservationStatsByCoiffeur']);

        Route::get('/populaires/{id_coiffeur}', [ApiReservationsController::class, 'topServices']);
    });

    Route::prefix('paiements')->group(function () {
        Route::get('/user/{id_utilisateur}', [ApiPaiementsController::class, 'getPaiementByUser']);
        Route::get('/reservation/{id_reservation}', [ApiPaiementsController::class, 'getPaiementStatusByReservation']);
        Route::get('/commande/{id_commande}', [ApiPaiementsController::class, 'getPaiementStatusByCommande']);
        Route::post('/', [ApiPaiementsController::class, 'store']);
        Route::put('/{id_paiement}', [ApiPaiementsController::class, 'update']);
        Route::delete('/{id_paiement}', [ApiPaiementsController::class, 'destroy']);
    });

    Route::prefix('produits')->group(function () {
        Route::post('/', [ApiProduitsController::class, 'store']);
        // POST (pas PUT) : PHP ne remplit $_FILES que pour les requêtes POST,
        // indispensable pour l'upload de la photo lors d'une modification.
        Route::post('/{id}', [ApiProduitsController::class, 'update']);
        Route::delete('/{id}', [ApiProduitsController::class, 'destroy']);
    });

    Route::prefix('commandes')->group(function () {
        Route::post('/', [ApiCommandesController::class, 'store']);
        Route::get('/client/{id_client}', [ApiCommandesController::class, 'getCommandesByClient']);
        Route::get('/coiffeuse/{id_coiffeur}', [ApiCommandesController::class, 'getCommandesByCoiffeuse']);
        Route::put('/expedier/{id_commande}', [ApiCommandesController::class, 'expedier']);
        Route::put('/livrer/{id_commande}', [ApiCommandesController::class, 'livrer']);
    });

    Route::prefix('gains-coiffeuses')->group(function () {
        Route::get('/user/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getGainsByUser']);
        Route::post('/', [ApiGainsCoiffeuseController::class, 'store']);
        Route::put('/{id_gain_coiffeuse}', [ApiGainsCoiffeuseController::class, 'update']);
        Route::delete('/{id_gain_coiffeuse}', [ApiGainsCoiffeuseController::class, 'destroy']);
        Route::get('/evolution-annuelle/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getEvolutionAnnuelle']);
        Route::get('/revenus-par-service/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getRevenusParService']);
    });

    Route::prefix('abonnement')->group(function () {
        Route::get('/{id_coiffeur}', [ApiAbonnementController::class, 'getStatut']);
        Route::put('/{id_coiffeur}', [ApiAbonnementController::class, 'changerFormule']);
        Route::get('/{id_coiffeur}/historique', [ApiAbonnementController::class, 'historique']);
    });

    Route::prefix('avis')->group(function () {
        Route::post('/', [ApiAvisController::class, 'store']);
        Route::put('/{id_avis}', [ApiAvisController::class, 'update']);
        Route::delete('/{id_avis}', [ApiAvisController::class, 'destroy']);
    });

    Route::prefix('favoris')->group(function () {
        Route::post('/ajouter', [ApiClientFavoriteController::class, 'addFavorite']);
        Route::delete('/supprimer', [ApiClientFavoriteController::class, 'removeFavorite']);
        Route::get('/client/{client_id}', [ApiClientFavoriteController::class, 'getFavoritesByClient']);
        Route::post('/verifier', [ApiClientFavoriteController::class, 'isFavorite']);
    });

    Route::get('/dashboard/{id_coiffeur}', [ApiDashboardCoiffeuseController::class, 'index']);
});
