<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Connecteur Twilio pour l'envoi réel de SMS (codes OTP).
 *
 * Utilise directement l'API REST de Twilio (pas le SDK officiel) pour
 * rester cohérent avec le style des autres connecteurs (JekoGateway,
 * RealStripeGateway), qui passent tous par Illuminate\Http\Client.
 *
 * Côte d'Ivoire : le réseau MTN refuse les SMS envoyés depuis un numéro
 * (sender ID numérique) ; il faut un sender ID alphanumérique (ex.
 * "AFROLIA"), qui doit être pré-enregistré auprès de Twilio avant de
 * fonctionner (processus de vérification, documents sur l'entreprise).
 * TWILIO_FROM doit contenir ce sender ID une fois l'enregistrement
 * terminé — tant qu'il ne l'est pas, les envois vers MTN échoueront côté
 * Twilio même avec des identifiants API valides.
 */
class TwilioSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
    ) {
    }

    public function envoyer(string $telephone, string $message): bool
    {
        try {
            $reponse = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'To' => $telephone,
                    'From' => $this->from,
                    'Body' => $message,
                ]);

            if ($reponse->failed()) {
                Log::error('Échec de l\'envoi SMS Twilio', [
                    'telephone' => $telephone,
                    'statut' => $reponse->status(),
                    'reponse' => $reponse->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Exception lors de l\'envoi SMS Twilio', [
                'telephone' => $telephone,
                'erreur' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
