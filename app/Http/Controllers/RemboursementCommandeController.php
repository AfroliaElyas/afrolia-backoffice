<?php

namespace App\Http\Controllers;

use App\Models\Commandes;
use App\Models\Paiements;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pendant de RemboursementController, pour les commandes boutique
 * (demandé le 04/11/2026, suite à l'audit financier : aucun moyen
 * n'existait de rembourser une commande déjà payée).
 *
 * La logique de dispatch Stripe/Mobile Money est délibérément DUPLIQUÉE à
 * l'identique depuis RemboursementController::traiter() plutôt
 * qu'extraite en commun : ce contrôleur gère de l'argent réel et est déjà
 * couvert par des tests ; une extraction aurait représenté un risque de
 * régression sur le flux réservations pour un gain de factorisation
 * limité (voir la consigne de cette tâche, qui autorise explicitement ce
 * choix).
 *
 * Contrairement aux réservations, pas de workflow de litige/rejet
 * (rejeter/confirmerVerification) : non demandé pour les commandes, et le
 * stock est déjà restitué dès l'ouverture du dossier côté API
 * (ApiCommandesController::ouvrirDossierRemboursement), pas ici.
 */
class RemboursementCommandeController extends Controller
{
    public function __construct(
        private readonly StripeGatewayInterface $stripeGateway,
        private readonly MobileMoneyGatewayInterface $mobileMoneyGateway
    ) {
    }

    public function traiter(string $id)
    {
        // Réclame le dossier avant tout appel réseau, même principe que
        // RemboursementController::traiter (double clic, deux admins).
        $commande = DB::transaction(function () use ($id) {
            $commande = Commandes::whereKey($id)->lockForUpdate()->first();

            if (!$commande || $commande->statut_remboursement !== 'en_attente') {
                return null;
            }

            $commande->update(['statut_remboursement' => 'en_cours']);

            return $commande;
        });

        if (!$commande) {
            return back()->withErrors([
                'Ce dossier n\'est plus "à traiter" (déjà en cours, déjà remboursé, ou aucun remboursement dû) : aucune action effectuée.',
            ]);
        }

        $commande->ajouterHistoriqueRemboursement('traitement_demarre');

        $paiement = Paiements::where('id_commande', $id)
            ->where('status', 'succeeded')
            ->latest('id_paiement')
            ->first();

        if (!$paiement) {
            return $this->remettreEnAttenteEtEchouer($commande, 'Aucun paiement réussi trouvé pour cette commande.');
        }

        // La cliente paie exactement montant_total (pas de commission
        // ajoutée à sa charge pour la boutique) : remboursement intégral
        // quel que soit qui a annulé.
        $montantARembourser = (float) $paiement->amount;

        if ($paiement->payment_method === 'stripe') {
            if (!$paiement->payment_intent_id) {
                return $this->remettreEnAttenteEtEchouer($commande, 'Ce paiement Stripe ne référence aucun PaymentIntent, remboursement impossible automatiquement.');
            }

            try {
                $this->stripeGateway->refund(
                    $paiement->payment_intent_id,
                    $montantARembourser,
                    idempotencyKey: "remboursement-commande-paiement-{$paiement->id_paiement}"
                );
            } catch (\Throwable $e) {
                Log::error('Échec du remboursement Stripe (commande)', ['id_paiement' => $paiement->id_paiement, 'erreur' => $e->getMessage()]);

                return $this->remettreEnAttenteEtEchouer($commande, 'Le remboursement Stripe a échoué : ' . $e->getMessage(), $e->getMessage());
            }
        } elseif ($paiement->payment_method === 'mobile_money') {
            if (!$paiement->mobile_money_telephone || !$paiement->mobile_money_operateur) {
                return $this->remettreEnAttenteEtEchouer($commande, 'Ce paiement Mobile Money ne précise pas de numéro/opérateur d\'origine, remboursement automatique impossible.');
            }

            $client = DB::table('users_app')->where('id_user_app', $commande->id_client)->first();
            $nomClient = trim(($client->name ?? '') . ' ' . ($client->last_name ?? '')) ?: 'Client Afrolia';

            // Référence stable (pas de suffixe aléatoire), rattachée à cette
            // commande via commandes.remboursement_reference, pour que le
            // webhook Jèko de confirmation/échec du transfert retrouve ce
            // dossier (voir ApiPaiementsController::traiterWebhookTransfertRemboursement).
            $reference = self::referenceTransfertJekoCommande($paiement->id_paiement);
            $commande->update(['remboursement_reference' => $reference]);

            try {
                $resultat = $this->mobileMoneyGateway->refund(
                    destinataire: [
                        'nom' => $nomClient,
                        'telephone' => $paiement->mobile_money_telephone,
                        'operateur' => $paiement->mobile_money_operateur,
                    ],
                    montant: $montantARembourser,
                    reference: $reference
                );

                if (($resultat['status'] ?? null) === 'error') {
                    throw new \RuntimeException('Jèko a refusé le transfert de remboursement.');
                }
            } catch (\Throwable $e) {
                Log::error('Échec du remboursement Mobile Money (commande)', ['id_paiement' => $paiement->id_paiement, 'erreur' => $e->getMessage()]);

                return $this->remettreEnAttenteEtEchouer($commande, 'Le remboursement Mobile Money a échoué : ' . $e->getMessage(), $e->getMessage());
            }
        } else {
            return $this->remettreEnAttenteEtEchouer($commande, 'Moyen de paiement non pris en charge pour un remboursement automatique.');
        }

        DB::transaction(function () use ($id) {
            Commandes::whereKey($id)->lockForUpdate()->update([
                'statut_paiement' => 'rembourse',
                'statut_remboursement' => 'traite',
            ]);
        });

        DB::table('paiements')
            ->where('id_paiement', $paiement->id_paiement)
            ->update([
                'status' => 'refunded',
                'processed_at' => now(),
            ]);

        $commande->ajouterHistoriqueRemboursement('rembourse', "Montant : {$montantARembourser} FCFA");

        return back()->with('succes', 'Le remboursement a été traité et le client a été remboursé.');
    }

    /**
     * Même format que RemboursementController::referenceTransfertJeko, avec
     * un préfixe distinct (COMMANDE vs PAIEMENT) pour qu'une référence de
     * remboursement de réservation et une de commande ne puissent jamais
     * entrer en collision même si elles partageaient un jour le même
     * id_paiement.
     */
    public static function referenceTransfertJekoCommande(int $idPaiement): string
    {
        return "REMB-COMMANDE-{$idPaiement}";
    }

    private function remettreEnAttenteEtEchouer(Commandes $commande, string $messageErreur, ?string $detailLog = null)
    {
        $commande->update(['statut_remboursement' => 'en_attente']);
        $commande->ajouterHistoriqueRemboursement('echec', $detailLog ?? $messageErreur);

        return back()->withErrors([$messageErreur]);
    }
}
