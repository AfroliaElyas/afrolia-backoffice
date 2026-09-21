<?php

namespace App\Console\Commands;

use App\Models\UsersApp;
use App\Services\AbonnementService;
use Illuminate\Console\Command;

class PreleverAbonnementsMensuels extends Command
{
    protected $signature = 'abonnements:prelever';

    protected $description = "Prélève l'abonnement mensuel des coiffeuses en formule payante arrivées à échéance, et repasse en formule Gratuite celles dont le solde est insuffisant";

    public function handle(AbonnementService $abonnements): int
    {
        $coiffeuses = UsersApp::where('role', 'hair')
            ->whereIn('formule_abonnement', ['standard', 'premium'])
            ->whereNotNull('prochain_prelevement_le')
            ->where('prochain_prelevement_le', '<=', now())
            ->get();

        $reussis = 0;
        $echecs = 0;

        foreach ($coiffeuses as $coiffeuse) {
            $resultat = $abonnements->preleverPour($coiffeuse, $coiffeuse->formule_abonnement);

            $resultat['success'] ? $reussis++ : $echecs++;
        }

        $this->info("{$reussis} prélèvement(s) d'abonnement réussi(s), {$echecs} échec(s) (repassage en formule Gratuite).");

        return self::SUCCESS;
    }
}
