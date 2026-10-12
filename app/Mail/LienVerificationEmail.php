<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LienVerificationEmail extends Mailable
{
    use SerializesModels;

    public readonly string $lien;

    public function __construct(string $tokenBrut)
    {
        $this->lien = url('/verifier-email/' . $tokenBrut);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmez votre adresse e-mail Afrolia',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Pour confirmer votre adresse e-mail Afrolia, cliquez sur le lien ci-dessous :</p>"
                . "<p><a href=\"{$this->lien}\">{$this->lien}</a></p>"
                . '<p>Ce lien est valable 24 heures. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.</p>',
        );
    }
}
