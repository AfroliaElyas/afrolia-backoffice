<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Prévient l'administratrice quand sauvegarde:externe échoue (base et/ou
 * photos) : sans ça, l'échec d'une tâche planifiée quotidienne ne casse
 * rien de visible sur le site et peut passer inaperçu indéfiniment — c'est
 * exactement ce qui s'est produit pendant tout l'épisode Backblaze du
 * 08-10/11/2026 (voir SauvegardeExterneCommand::alerterEnCasEchec).
 */
class AlerteSauvegardeExterneEchouee extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly bool $resultatBase,
        public readonly bool $resultatPhotos,
        public readonly string $dateExecution,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Afrolia : échec de la sauvegarde externe',
        );
    }

    public function content(): Content
    {
        $etat = fn (bool $ok) => $ok ? 'OK' : 'ÉCHEC';

        return new Content(
            htmlString: "<p>La sauvegarde externe (Backblaze) du {$this->dateExecution} a échoué.</p>"
                . "<p>Base de données : <strong>{$etat($this->resultatBase)}</strong><br>"
                . "Photos : <strong>{$etat($this->resultatPhotos)}</strong></p>"
                . '<p>Voir <code>storage/logs/scheduler-sauvegarde-externe.log</code> sur le serveur pour le détail de l\'erreur. '
                . "Les données ne sont pas perdues : seule la copie hors serveur de ce jour est manquante.</p>",
        );
    }
}
