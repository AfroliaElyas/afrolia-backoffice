<?php

use App\Http\Controllers\Api\ApiAvisController;
use App\Http\Controllers\Api\ApiClientFavoriteController;
use App\Http\Controllers\Api\ApiDashboardCoiffeuseController;
use App\Http\Controllers\Api\ApiGainsCoiffeuseController;
use App\Http\Controllers\Api\ApiPaiementsController;
use App\Http\Controllers\Api\ApiReservationsController;
use App\Http\Controllers\Api\ApiSalonController;
use App\Http\Controllers\Api\ApiSecurityController;
use App\Http\Controllers\Api\ApiServicesController;
use App\Http\Controllers\Api\ApiSociauxController;
use App\Http\Controllers\Api\ApiUserSalonController;
use App\Http\Controllers\Api\ApiUtilisateursController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentifications
Route::post('login', [ApiUtilisateursController::class, 'login']);
Route::post('register', [ApiUtilisateursController::class, 'register']);
Route::post('otp', [ApiUtilisateursController::class, 'demanderOtpReset']);
Route::post('reset', [ApiUtilisateursController::class, 'resetPasswordWithOtp']);
Route::post('update/{id}', [ApiUtilisateursController::class, 'update']);
Route::delete('delete/{id}', [ApiUtilisateursController::class, 'supprimerCompte']);
Route::get('delete/{id}', [ApiUtilisateursController::class, 'supprimerCompte']);

// Confidentialite
Route::get('help', [ApiSecurityController::class, 'helps']);
Route::get('security', [ApiSecurityController::class, 'security']);

// Salons Complets
Route::post('updateinfobasic/{id}', [ApiUserSalonController::class, 'updateInfoBasic']);
Route::post('updatepresentation/{id}', [ApiUserSalonController::class, 'updatePresentation']);
Route::get('presentation/{id}', [ApiUserSalonController::class, 'presentation']);
Route::get('specialites', [ApiUserSalonController::class, 'specialites']);
Route::get('langues', [ApiUserSalonController::class, 'langues']);
Route::get('associationspecialite/{id}', [ApiUserSalonController::class, 'assoSpecialite']);
Route::get('associationlangue/{id}', [ApiUserSalonController::class, 'assoLangue']);
Route::post('hair/specialites/associer', [ApiUserSalonController::class, 'associerSpecialitesUtilisateur']);
Route::post('hair/langues/associer', [ApiUserSalonController::class, 'associerLanguesUtilisateur']);
Route::get('disponibilites/{id}', [ApiUserSalonController::class, 'getDisponibilitesByUser']);
Route::post('disponibilites', [ApiUserSalonController::class, 'saveDisponibilites']);
Route::get('jours-heures', [ApiUserSalonController::class, 'getJoursEtHeures']);
Route::get('hair/services/{id}', [ApiServicesController::class, 'getServicesByUser']);
Route::post('hair/services', [ApiServicesController::class, 'createServices']);
Route::put('services/{id}', [ApiServicesController::class, 'updateService']);
Route::delete('services/{id}', [ApiServicesController::class, 'deleteService']);
Route::get('hair/sociaux/{id}', [ApiSociauxController::class, 'getSociauxByUser']);
Route::post('hair/sociaux', [ApiSociauxController::class, 'saveSociaux']);
Route::get('hair/gallery/{id}', [ApiSociauxController::class, 'getGalleryByUser']);
Route::post('hair/gallery', [ApiSociauxController::class, 'createGallery']);
Route::put('gallery/{id}', [ApiSociauxController::class, 'updateGallery']);
Route::delete('gallery/{id}', [ApiSociauxController::class, 'deleteGallery']);

Route::get('salons', [ApiSalonController::class, 'getCoiffeursAvecStatut']);
Route::get('salons/{id_coiffeur}', [ApiSalonController::class, 'getProfilCoiffeur']);

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
    Route::post('/', [ApiPaiementsController::class, 'store']);
    Route::put('/{id_paiement}', [ApiPaiementsController::class, 'update']);
    Route::delete('/{id_paiement}', [ApiPaiementsController::class, 'destroy']);
});
Route::post('/stripe/webhook', [ApiPaiementsController::class, 'stripeWebhook']);
Route::post('/mobile-money/webhook', [ApiPaiementsController::class, 'mobileMoneyWebhook']);
Route::prefix('gains-coiffeuses')->group(function () {
    Route::get('/user/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getGainsByUser']);
    Route::post('/', [ApiGainsCoiffeuseController::class, 'store']);
    Route::put('/{id_gain_coiffeuse}', [ApiGainsCoiffeuseController::class, 'update']);
    Route::delete('/{id_gain_coiffeuse}', [ApiGainsCoiffeuseController::class, 'destroy']);
    Route::get('/evolution-annuelle/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getEvolutionAnnuelle']);
    Route::get('/revenus-par-service/{id_utilisateur}', [ApiGainsCoiffeuseController::class, 'getRevenusParService']);
});
Route::prefix('avis')->group(function () {
    Route::get('/coiffeuse/{id_coiffeuse}', [ApiAvisController::class, 'getAvisByCoiffeuse']);
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
