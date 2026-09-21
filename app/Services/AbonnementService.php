<?php

namespace App\Services;

use App\Models\AbonnementPaiement;
use App\Models\Gains;
use App\Models\UsersApp;
use Illuminate\Support\Facades\DB;

class AbonnementService
{
    public const FORMULES = ['gratuit', 'standard', 'premium'];

    public function prix(string $formule): float
    {
        return match ($formule) {
            'standard' => $this->parametre('prix_abonnement_standard', 4500),
            'premium' => $this->parametre('prix_abonnement_premium', 5500),
            default => 0.0,
        };
    }

    public function tauxCommission(string $formule): float
    {
        return match ($formule) {
            'standard' => $this->parametre('commission_standard', 3) / 100,
            'premium' => $this->parametre('commission_premium', 0) / 100,
            default => $this->parametre('commission_gratuit', 5) / 100,
        };
    }

    public function tauxCommissionPourCoiffeuse(UsersApp $coiffeuse): float
    {
        return $this->tauxCommission($coiffeuse->formule_abonnement ?: 'gratuit');
    }

    /**
     * Gains n'a pas de colonne de solde : le total d'une coiffeuse est
     * calculé à la volée en sommant ses gains disponibles, moins les
     * abonnements déjà prélevés avec succès (même principe que
     * ApiGainsCoiffeuseController::getGainsByUser).
     */
    public function soldeDisponible(int $idCoiffeur): float
    {
        $totalGains = Gains::where('id_coiffeur', $idCoiffeur)
            ->where('statut', '!=', 'en_attente')
            ->sum('montant_net');

        $totalPreleve = AbonnementPaiement::where('id_coiffeur', $idCoiffeur)
            ->where('statut', 'reussi')
            ->sum('montant');

        return (float) $totalGains - (float) $totalPreleve;
    }

    /**
     * Tente un prélèvement pour la formule donnée sur le solde de la
     * coiffeuse.
     * - Solde suffisant : historise le prélèvement, active/renouvelle la
     *   formule et avance la date de prochain prélèvement d'un mois.
     * - Solde insuffisant : historise l'échec et repasse automatiquement
     *   la coiffeuse en formule Gratuite.
     *
     * @return array{success: bool, montant?: float, solde?: float, prix?: float}
     */
    public function preleverPour(UsersApp $coiffeuse, string $formule): array
    {
        $prix = $this->prix($formule);

        if ($prix <= 0) {
            $coiffeuse->update(['formule_abonnement' => 'gratuit', 'prochain_prelevement_le' => null]);

            return ['success' => true, 'montant' => 0.0];
        }

        $solde = $this->soldeDisponible($coiffeuse->id_user_app);

        if ($solde < $prix) {
            AbonnementPaiement::create([
                'id_coiffeur' => $coiffeuse->id_user_app,
                'formule' => $formule,
                'montant' => $prix,
                'statut' => 'echec',
                'date_prelevement' => now(),
            ]);

            $coiffeuse->update(['formule_abonnement' => 'gratuit', 'prochain_prelevement_le' => null]);

            return ['success' => false, 'solde' => $solde, 'prix' => $prix];
        }

        AbonnementPaiement::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => $formule,
            'montant' => $prix,
            'statut' => 'reussi',
            'date_prelevement' => now(),
        ]);

        $coiffeuse->update([
            'formule_abonnement' => $formule,
            'prochain_prelevement_le' => now()->addMonthNoOverflow(),
        ]);

        return ['success' => true, 'montant' => $prix];
    }

    private function parametre(string $cle, float $defaut): float
    {
        $valeur = DB::table('parametres')->where('cle', $cle)->value('valeur');

        return $valeur !== null ? (float) $valeur : $defaut;
    }
}
