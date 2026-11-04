<?php

namespace App\Services;

use App\Models\AbonnementPaiement;
use App\Models\Gains;
use App\Models\UsersApp;
use Illuminate\Support\Facades\DB;

class AbonnementService
{
    public const FORMULES = ['gratuit', 'premium'];

    public function prix(string $formule): float
    {
        return match ($formule) {
            'premium' => $this->parametre('prix_abonnement_premium', 4500),
            default => 0.0,
        };
    }

    public function tauxCommission(string $formule): float
    {
        return match ($formule) {
            'premium' => $this->parametre('commission_premium', 6) / 100,
            default => $this->parametre('commission_gratuit', 8) / 100,
        };
    }

    public function tauxCommissionPourCoiffeuse(UsersApp $coiffeuse): float
    {
        return $this->tauxCommission($coiffeuse->formule_abonnement ?: 'gratuit');
    }

    /**
     * Taux de la boutique (produits) : même taux que les prestations
     * (8 % Gratuite / 6 % Premium), une seule source de vérité pour ne
     * plus jamais pouvoir diverger silencieusement. Avant le 18/10/2026,
     * la boutique utilisait des paramètres séparés (commission_boutique_*)
     * restés à 5 %/3 % sans avoir jamais été réellement validés — ce
     * n'était pas un choix délibéré de maintenir deux taux différents.
     * Le mécanisme de perception reste différent (ajoutée au prix payé
     * par la cliente, alors que pour les prestations elle est déduite des
     * gains de la coiffeuse) : seul le taux est désormais unifié.
     */
    public function tauxCommissionBoutique(string $formule): float
    {
        return $this->tauxCommission($formule);
    }

    public function tauxCommissionBoutiquePourCoiffeuse(UsersApp $coiffeuse): float
    {
        return $this->tauxCommissionBoutique($coiffeuse->formule_abonnement ?: 'gratuit');
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
     * - Passage à Gratuit avec des jours Premium déjà payés en cours : ne
     *   repasse PAS immédiatement en Gratuite (elle a payé cette période) —
     *   désactive seulement le renouvellement automatique ; c'est la tâche
     *   planifiée (PreleverAbonnementsMensuels) qui la repassera en
     *   Gratuite à l'échéance déjà payée (demandé le 04/11/2026, suite à
     *   l'audit financier : avant ce correctif, changer de formule faisait
     *   perdre les jours déjà payés).
     * - Repasser à Premium alors qu'il reste des jours déjà payés : ne
     *   prélève rien une deuxième fois, réactive juste le renouvellement
     *   automatique (annule un passage à Gratuite programmé).
     * - Solde suffisant (cas normal) : historise le prélèvement, active la
     *   formule et avance la date de prochain prélèvement (voir
     *   activerPeriode).
     * - Solde insuffisant : historise l'échec et repasse automatiquement
     *   la coiffeuse en formule Gratuite.
     *
     * @return array{success: bool, montant?: float, solde?: float, prix?: float, effectif_le?: \Carbon\Carbon}
     */
    public function preleverPour(UsersApp $coiffeuse, string $formule): array
    {
        $prix = $this->prix($formule);
        $aDesJoursRestants = $coiffeuse->formule_abonnement === 'premium' && $coiffeuse->prochain_prelevement_le?->isFuture();

        if ($prix <= 0) {
            if ($aDesJoursRestants) {
                $coiffeuse->update(['renouvellement_automatique' => false]);

                return ['success' => true, 'montant' => 0.0, 'effectif_le' => $coiffeuse->prochain_prelevement_le];
            }

            $coiffeuse->update([
                'formule_abonnement' => 'gratuit',
                'prochain_prelevement_le' => null,
                'renouvellement_automatique' => true,
            ]);

            return ['success' => true, 'montant' => 0.0];
        }

        if ($aDesJoursRestants) {
            $coiffeuse->update(['renouvellement_automatique' => true]);

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

            $coiffeuse->update([
                'formule_abonnement' => 'gratuit',
                'prochain_prelevement_le' => null,
                'renouvellement_automatique' => true,
            ]);

            return ['success' => false, 'solde' => $solde, 'prix' => $prix];
        }

        AbonnementPaiement::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'formule' => $formule,
            'montant' => $prix,
            'statut' => 'reussi',
            'date_prelevement' => now(),
        ]);

        $this->activerPeriode($coiffeuse, $formule);

        return ['success' => true, 'montant' => $prix];
    }

    /**
     * Active (ou prolonge) une période payante pour une coiffeuse : source
     * unique de la logique de prolongation, utilisée à la fois par
     * preleverPour() (prélèvement sur le solde de gains) et par
     * ApiPaiementsController::activerAbonnementPaye() (paiement direct par
     * carte/Mobile Money), pour qu'elles ne puissent jamais diverger.
     *
     * Si la coiffeuse est déjà Premium avec des jours payés en cours, la
     * nouvelle période part de cette échéance existante plutôt que
     * d'écraser les jours restants avec "maintenant + 1 mois".
     */
    public function activerPeriode(UsersApp $coiffeuse, string $formule): \Carbon\Carbon
    {
        $depart = ($coiffeuse->formule_abonnement === 'premium' && $coiffeuse->prochain_prelevement_le?->isFuture())
            ? $coiffeuse->prochain_prelevement_le
            : now();

        $prochain = $depart->copy()->addMonthNoOverflow();

        $coiffeuse->update([
            'formule_abonnement' => $formule,
            'prochain_prelevement_le' => $prochain,
            'renouvellement_automatique' => true,
        ]);

        return $prochain;
    }

    private function parametre(string $cle, float $defaut): float
    {
        $valeur = DB::table('parametres')->where('cle', $cle)->value('valeur');

        return $valeur !== null ? (float) $valeur : $defaut;
    }
}
