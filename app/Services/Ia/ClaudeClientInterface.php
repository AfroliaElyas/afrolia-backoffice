<?php

namespace App\Services\Ia;

interface ClaudeClientInterface
{
    /**
     * Envoie un message unique (sans historique) à Claude avec le prompt
     * système donné et renvoie le texte de la réponse.
     */
    public function repondre(string $promptSysteme, string $question): string;
}
