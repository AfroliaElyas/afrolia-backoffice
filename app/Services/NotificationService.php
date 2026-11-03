<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\UsersApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de notifications push (Firebase Cloud Messaging), demandées le
 * 03/11/2026 : nouvelles réservations, confirmations, annulations,
 * rappels de rendez-vous, nouveaux messages, paiements.
 *
 * Tant que FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS_JSON ne sont pas
 * renseignées (projet Firebase à créer par Ruth), chaque envoi est
 * ignoré silencieusement (journalisé en debug) : ne doit jamais faire
 * échouer l'action qui déclenche la notification (créer une réservation,
 * envoyer un message, confirmer un paiement...).
 *
 * Jamais de donnée sensible dans $titre/$corps : ces deux champs
 * s'affichent sur l'écran verrouillé de l'appareil (ex. jamais le texte
 * d'un message, voir ApiMessagesController).
 */
class NotificationService
{
    public function envoyerAUtilisateur(?UsersApp $utilisateur, string $titre, string $corps, array $donnees = []): void
    {
        if (!$utilisateur) {
            return;
        }

        if (!$this->estConfigure()) {
            Log::debug("Notification push ignorée (Firebase non configuré) : {$titre}");
            return;
        }

        $accessToken = $this->obtenirJetonAcces();
        if ($accessToken === null) {
            return;
        }

        foreach ($utilisateur->deviceTokens()->get() as $deviceToken) {
            $this->envoyerAUnAppareil($deviceToken, $accessToken, $titre, $corps, $donnees);
        }
    }

    private function envoyerAUnAppareil(DeviceToken $deviceToken, string $accessToken, string $titre, string $corps, array $donnees): void
    {
        $projetId = config('services.firebase.project_id');

        $reponse = Http::withToken($accessToken)
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projetId}/messages:send", [
                'message' => [
                    'token' => $deviceToken->token,
                    'notification' => [
                        'title' => $titre,
                        'body' => $corps,
                    ],
                    'data' => array_map('strval', $donnees),
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                    ],
                ],
            ]);

        if ($reponse->failed()) {
            $erreur = $reponse->json('error.status');

            // Jeton expiré/désinstallé côté appareil : Firebase le signale
            // ainsi, jamais réutilisable ensuite — on l'oublie pour ne pas
            // réessayer indéfiniment sur un appareil qui ne recevra plus
            // jamais rien (voir aussi la déduplication à l'enregistrement,
            // ApiDeviceTokenController).
            if (in_array($erreur, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                $deviceToken->delete();
            }

            Log::warning('Échec d\'envoi de notification push', [
                'id_user_app' => $deviceToken->id_user_app,
                'erreur' => $erreur,
            ]);
        }
    }

    private function estConfigure(): bool
    {
        return filled(config('services.firebase.project_id')) && filled(config('services.firebase.credentials_json'));
    }

    /**
     * Jeton d'accès OAuth2 pour l'API FCM, obtenu à partir du compte de
     * service Firebase (JWT signé RS256, échangé contre un jeton auprès de
     * Google). Mis en cache 55 minutes (durée de vie réelle : 1h) pour ne
     * jamais ré-authentifier à chaque notification envoyée.
     */
    private function obtenirJetonAcces(): ?string
    {
        return Cache::remember('firebase_access_token', 3300, function () {
            try {
                $identifiants = json_decode((string) config('services.firebase.credentials_json'), true);

                if (
                    !is_array($identifiants)
                    || empty($identifiants['client_email'])
                    || empty($identifiants['private_key'])
                ) {
                    Log::warning('FIREBASE_CREDENTIALS_JSON invalide ou incomplet');
                    return null;
                }

                $jwt = $this->construireJwt($identifiants['client_email'], $identifiants['private_key']);

                $reponse = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($reponse->failed()) {
                    Log::warning("Échec d'obtention du jeton d'accès Firebase", ['reponse' => $reponse->body()]);
                    return null;
                }

                return $reponse->json('access_token');
            } catch (\Throwable $e) {
                Log::warning("Exception lors de l'obtention du jeton d'accès Firebase : " . $e->getMessage());
                return null;
            }
        });
    }

    private function construireJwt(string $clientEmail, string $clePrivee): string
    {
        $maintenant = time();

        $entete = $this->encoderBase64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $revendications = $this->encoderBase64Url((string) json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $maintenant,
            'exp' => $maintenant + 3600,
        ]));

        $donneesSignees = "{$entete}.{$revendications}";

        openssl_sign($donneesSignees, $signature, $clePrivee, OPENSSL_ALGO_SHA256);

        return "{$donneesSignees}." . $this->encoderBase64Url($signature);
    }

    private function encoderBase64Url(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }
}
