<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LienReinitialisationMotDePasse extends Mailable
{
    use SerializesModels;

    public readonly string $lien;

    public function __construct(string $tokenBrut)
    {
        $this->lien = url('/reinitialiser-mot-de-passe/' . $tokenBrut);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisez votre mot de passe Afrolia',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Vous avez demandé à réinitialiser votre mot de passe Afrolia. Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe :</p>"
                . "<p><a href=\"{$this->lien}\">{$this->lien}</a></p>"
                . '<p>Ce lien est valable 60 minutes et ne fonctionne qu\'une seule fois. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail : votre mot de passe actuel reste inchangé.</p>',
        );
    }
}
