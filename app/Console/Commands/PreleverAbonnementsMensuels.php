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
            ->where('formule_abonnement', 'premium')
            ->whereNotNull('prochain_prelevement_le')
            ->where('prochain_prelevement_le', '<=', now())
            ->get();

        $reussis = 0;
        $echecs = 0;
        $annulationsSurDemande = 0;

        foreach ($coiffeuses as $coiffeuse) {
            // Passage à Gratuite déjà demandé (voir AbonnementService::preleverPour) :
            // ce n'est pas un échec de paiement, aucun prélèvement à tenter.
            if (!$coiffeuse->renouvellement_automatique) {
                $coiffeuse->update([
                    'formule_abonnement' => 'gratuit',
                    'prochain_prelevement_le' => null,
                    'renouvellement_automatique' => true,
                ]);
                $annulationsSurDemande++;
                continue;
            }

            $resultat = $abonnements->preleverPour($coiffeuse, $coiffeuse->formule_abonnement);

            $resultat['success'] ? $reussis++ : $echecs++;
        }

        $this->info(
            "{$reussis} prélèvement(s) d'abonnement réussi(s), {$echecs} échec(s) (repassage en formule Gratuite), "
            . "{$annulationsSurDemande} passage(s) en formule Gratuite sur demande."
        );

        return self::SUCCESS;
    }
}
