<?php

namespace App\Console\Commands;

use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rappel de rendez-vous (notification push), demandé le 03/11/2026 :
 * notifie la cliente et la coiffeuse environ 1h avant une réservation
 * confirmée.
 *
 * rappel_envoye_at empêche tout doublon (y compris si la commande est
 * relancée manuellement ou que deux exécutions planifiées se
 * chevauchent) : marqué immédiatement après l'envoi de chaque
 * réservation traitée, jamais groupé à la fin — une interruption en
 * cours de route ne fait donc jamais renvoyer un rappel déjà parti.
 */
class EnvoyerRappelsReservationCommand extends Command
{
    protected $signature = 'rappels:envoyer';

    protected $description = "Envoie un rappel de rendez-vous aux réservations confirmées dont l'heure approche";

    public function handle(NotificationService $notifications): int
    {
        $maintenant = Carbon::now();
        $horizon = $maintenant->copy()->addHour();

        // Filtre large côté base de données (aujourd'hui et demain, pour
        // couvrir un rendez-vous juste après minuit) : la fenêtre précise
        // (entre maintenant et +1h) est appliquée ensuite en PHP, où
        // combiner date_reservation + heure_reservation est fiable quel
        // que soit le moteur de base de données (SQLite en test, MySQL en
        // production — voir phpunit.xml).
        $candidates = Reservations::where('statut', 'confirmee')
            ->whereNull('rappel_envoye_at')
            ->whereIn('date_reservation', [$maintenant->toDateString(), $maintenant->copy()->addDay()->toDateString()])
            ->get();

        $envoyes = 0;

        foreach ($candidates as $reservation) {
            try {
                $debut = Carbon::parse("{$reservation->date_reservation} {$reservation->heure_reservation}");
            } catch (\Throwable) {
                continue;
            }

            if ($debut->lessThan($maintenant) || $debut->greaterThan($horizon)) {
                continue;
            }

            $notifications->envoyerAUtilisateur(
                UsersApp::find($reservation->id_client),
                'Rappel de rendez-vous',
                'Votre réservation commence dans environ 1 heure.',
                ['type' => 'reservation', 'id_reservation' => $reservation->id_reservation],
            );

            $notifications->envoyerAUtilisateur(
                UsersApp::find($reservation->id_coiffeur),
                'Rappel de rendez-vous',
                'Une réservation commence dans environ 1 heure.',
                ['type' => 'reservation', 'id_reservation' => $reservation->id_reservation],
            );

            $reservation->update(['rappel_envoye_at' => $maintenant]);
            $envoyes++;
        }

        $this->info("Rappels envoyés : {$envoyes}");

        return self::SUCCESS;
    }
}
