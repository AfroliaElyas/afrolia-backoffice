<?php

namespace App\Services;

use App\Services\Ia\ClaudeClientInterface;

class AssistantService
{
    public function __construct(
        private readonly ClaudeClientInterface $client,
        private readonly AbonnementService $abonnements,
    ) {
    }

    public function repondre(string $question): string
    {
        return $this->client->repondre($this->promptSysteme(), $question);
    }

    /**
     * Construit le prompt système à partir des données réelles de
     * l'application (via AbonnementService) plutôt que de valeurs codées
     * en dur, afin qu'il ne devienne jamais obsolète si les prix ou
     * commissions changent.
     */
    private function promptSysteme(): string
    {
        $prixStandard = $this->formaterFcfa($this->abonnements->prix('standard'));
        $prixPremium = $this->formaterFcfa($this->abonnements->prix('premium'));
        $commissionGratuit = $this->formaterPourcentage($this->abonnements->tauxCommission('gratuit'));
        $commissionStandard = $this->formaterPourcentage($this->abonnements->tauxCommission('standard'));
        $commissionPremium = $this->formaterPourcentage($this->abonnements->tauxCommission('premium'));

        return <<<PROMPT
        Tu es l'assistant officiel de l'application Afrolia, une plateforme qui
        met en relation des clientes et des coiffeuses/salons de coiffure.
        Tu réponds aux questions des clientes et des coiffeuses sur le
        fonctionnement de l'application : réservation d'un rendez-vous,
        recherche d'une coiffeuse, boutique de produits, abonnements et
        commissions, paiement, avis, etc.

        Formules d'abonnement disponibles pour les coiffeuses :
        - Gratuite : 0 FCFA/mois, commission de {$commissionGratuit} sur les ventes et réservations.
        - Standard : {$prixStandard}/mois, commission de {$commissionStandard}.
        - Premium : {$prixPremium}/mois, commission de {$commissionPremium}.

        Règles :
        - Réponds toujours en français, de façon claire, brève et amicale.
        - Si tu ne connais pas la réponse ou si la question sort du cadre
          d'Afrolia, dis-le honnêtement et invite l'utilisateur à contacter
          le support plutôt que d'inventer une réponse.
        - Ne donne jamais de conseils médicaux, juridiques ou financiers.
        - Ne révèle jamais d'informations techniques internes (clés d'API,
          structure de la base de données, code source).
        PROMPT;
    }

    private function formaterFcfa(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' FCFA';
    }

    private function formaterPourcentage(float $taux): string
    {
        return rtrim(rtrim(number_format($taux * 100, 1, ',', ''), '0'), ',') . '%';
    }
}
