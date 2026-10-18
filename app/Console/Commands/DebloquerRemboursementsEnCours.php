<?php

namespace App\Console\Commands;

use App\Models\Reservations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Si le serveur s'arrête (déploiement, crash) entre la "réclamation" d'un
 * dossier de remboursement (passage à "en_cours", voir
 * RemboursementController::traiter) et la confirmation de son issue, ce
 * dossier reste bloqué "en_cours" indéfiniment, sans garantie que le
 * transfert ait réellement eu lieu ou non (demandé le 18/10/2026).
 *
 * IMPORTANT : ce cas ne remet PAS le dossier en "en_attente". Une
 * première version le faisait (avec un simple avertissement dans
 * l'historique), mais rien n'empêchait alors un·e administrateur·rice de
 * cliquer "Traiter" sans lire cet avertissement — ce qui aurait pu
 * déclencher un deuxième remboursement réel si le premier appel avait en
 * fait réussi avant la coupure. Le dossier passe donc à un état séparé,
 * "incertain", pour lequel ni "Traiter" ni "Rejeter" ne sont proposés par
 * RemboursementController (ses deux actions exigent "en_attente") : la
 * seule sortie possible est RemboursementController::confirmerVerification,
 * qui exige explicitement la preuve de ce qui a été vérifié auprès de
 * Stripe/Jèko avant de redevenir actionnable.
 */
class DebloquerRemboursementsEnCours extends Command
{
    protected $signature = 'remboursements:debloquer-en-cours {--minutes=15}';

    protected $description = "Marque \"incertain\" (ni traitable ni rejetable) les dossiers de remboursement restés \"en_cours\" après une interruption serveur, en attendant une vérification humaine explicite";

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

                $verrouillee->update(['statut_remboursement' => 'incertain']);
                $verrouillee->ajouterHistoriqueRemboursement(
                    'marque_incertain',
                    "Resté \"en_cours\" plus de {$minutes} minutes sans confirmation (interruption serveur probable) — " .
                    'issue réelle inconnue. Bloqué : ni "Traiter" ni "Rejeter" ne sont proposés tant qu\'une vérification explicite (via "Confirmer la vérification") n\'a pas été enregistrée.'
                );
            });
        }

        $this->info("{$bloques->count()} dossier(s) marqué(s) \"incertain\", à vérifier manuellement avant de pouvoir les retraiter.");

        return self::SUCCESS;
    }
}
