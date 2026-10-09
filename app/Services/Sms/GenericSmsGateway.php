<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Adaptateur en attente du choix définitif d'un fournisseur SMS. N'envoie
 * aucun SMS réel : jusqu'ici, le code OTP de réinitialisation de mot de
 * passe n'était que journalisé (Log::info), jamais transmis au téléphone
 * de la personne qui le demande, ce qui rendait l'oubli de mot de passe
 * impossible à résoudre pour une vraie utilisatrice.
 *
 * Retourne toujours false : le code appelant doit refuser explicitement
 * (comme pour GenericMobileMoneyGateway) plutôt que de prétendre qu'un
 * SMS a été envoyé. À remplacer par un adaptateur appelant réellement
 * l'API du fournisseur SMS retenu.
 */
class GenericSmsGateway implements SmsGatewayInterface
{
    public function envoyer(string $telephone, string $message): bool
    {
        Log::warning("SMS non envoyé (aucun fournisseur SMS configuré) à {$telephone} : {$message}");

        return false;
    }
}
