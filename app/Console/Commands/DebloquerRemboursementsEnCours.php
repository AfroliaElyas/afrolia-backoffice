<?php

namespace App\Console\Commands;

use App\Models\Reservations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Si le serveur s'arrête (déploiement, crash) entre la "réclamation" d'un
 * dossier de remboursement (passage à "en_cours", voir
 * RemboursementController::traiter) et la confirmation de son issue, ce
 * dossier reste bloqué "en_cours" indéfiniment : jamais revenu "en_attente"
 * pour qu'une nouvelle tentative le reprenne, mais sans garantie non plus
 * que le transfert ait réellement eu lieu (demandé le 18/10/2026).
 *
 * Contrairement à un échec normal (remis en "en_attente" directement par
 * traiter() ou par le webhook Jèko, avec la certitude que le transfert n'a
 * pas eu lieu), ce déblocage est volontairement SANS cette certitude : on
 * ne sait pas si l'appel à Stripe/Jèko avait déjà réussi au moment de
 * l'interruption. Le dossier est donc remis "en_attente" mais avec un
 * historique qui le dit explicitement, pour qu'une personne vérifie
 * manuellement (dashboard Stripe/Jèko pour la référence du paiement) avant
 * de cliquer "Traiter" à nouveau — jamais une nouvelle tentative
 * automatique.
 */
class DebloquerRemboursementsEnCours extends Command
{
    protected $signature = 'remboursements:debloquer-en-cours {--minutes=15}';

    protected $description = "Débloque les dossiers de remboursement restés \"en_cours\" après une interruption serveur, pour vérification manuelle avant nouvelle tentative";

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $bloques = Reservations::where('statut_remboursement', 'en_cours')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->get();

        foreach ($bloques as $reservation) {
            DB::transaction(function () use ($reservation, $minutes) {
                $verrouillee = Reservations::whereKey($reservation->id_reservation)->lockForUpdate()->first();

                // Revérifié sous verrou : si traiter() ou le webhook Jèko a
                // conclu entre la lecture ci-dessus et ce verrou, ne pas
                // écraser cette conclusion.
                if (!$verrouillee || $verrouillee->statut_remboursement !== 'en_cours') {
                    return;
                }

                $verrouillee->update(['statut_remboursement' => 'en_attente']);
                $verrouillee->ajouterHistoriqueRemboursement(
                    'debloque_sans_confirmation',
                    "Resté \"en_cours\" plus de {$minutes} minutes sans confirmation (interruption serveur probable) — " .
                    'VÉRIFIER MANUELLEMENT auprès de Stripe/Jèko si le transfert a réellement eu lieu avant de retraiter.'
                );
            });
        }

        $this->info("{$bloques->count()} dossier(s) débloqué(s), à vérifier manuellement avant nouvelle tentative.");

        return self::SUCCESS;
    }
}
