<?php

namespace App\Services\Sms;

interface SmsGatewayInterface
{
    /**
     * Envoie un SMS. Retourne true seulement si le message a réellement
     * été transmis au fournisseur SMS (pas seulement généré/journalisé).
     */
    public function envoyer(string $telephone, string $message): bool;
}
