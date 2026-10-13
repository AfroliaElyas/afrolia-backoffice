<?php

namespace App\Services;

use App\Models\Gains;
use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Suppression d'un compte utilisateur·rice, telle qu'elle doit se dérouler
 * partout dans l'application (auto-service depuis l'app, ou depuis le
 * back-office) : jamais un DELETE direct sur users_app, dont les clés
 * étrangères en cascade (reservations, paiements, gains, commandes,
 * versements, avis...) effaceraient immédiatement tout l'historique
 * financier, alors que les livres comptables et pièces justificatives
 * doivent être conservés dix ans (article 24 de l'AUDCIF).
 *
 * À la place : les données personnelles sont effacées du compte, qui reste
 * en base (anonymisé, connexion bloquée) pour que les réservations,
 * paiements et gains déjà existants restent intacts et consultables pour
 * la durée légale.
 */
class SuppressionCompteService
{
    /**
     * @throws SuppressionCompteBloqueeException si des réservations,
     *         remboursements ou gains encore en cours empêchent la
     *         suppression.
     */
    public function supprimer(UsersApp $utilisateur): void
    {
        $this->verifierAucunBlocage($utilisateur);

        $nomComplet = trim($utilisateur->name . ' ' . $utilisateur->last_name);
        $photo = $utilisateur->photo;

        DB::transaction(function () use ($utilisateur, $nomComplet) {
            // Fige l'identité telle qu'elle était sur les réservations déjà
            // existantes, avant de l'effacer du compte : les pièces
            // justificatives qui doivent continuer à l'indiquer ne
            // dépendent plus du compte (qui va être anonymisé).
            $utilisateur->reservationsClient()->update(['client_nom_facturation' => $nomComplet]);
            $utilisateur->reservationsCoiffeur()->update(['coiffeur_nom_facturation' => $nomComplet]);

            $utilisateur->tokens()->delete();

            $utilisateur->update([
                'name' => 'Compte supprimé',
                'last_name' => '',
                'phone' => 'supprime-' . $utilisateur->id_user_app,
                'email' => null,
                'presentation' => null,
                'adresse' => null,
                'commune' => null,
                'latitude' => null,
                'longitude' => null,
                'photo' => null,
                'payout_telephone' => null,
                'payout_operateur' => null,
                'password' => Hash::make(Str::random(40)),
                'statut' => 'Supprime',
            ]);
        });

        if ($photo) {
            $this->supprimerFichierPhoto($photo);
        }
    }

    private function verifierAucunBlocage(UsersApp $utilisateur): void
    {
        $reservationsNonSoldees = Reservations::where(function ($requete) use ($utilisateur) {
            $requete->where('id_client', $utilisateur->id_user_app)
                ->orWhere('id_coiffeur', $utilisateur->id_user_app);
        })
            ->where(function ($requete) {
                $requete->whereIn('statut', ['en_attente', 'confirmee', 'en_cours'])
                    ->orWhere('statut_remboursement', 'en_attente');
            })
            ->exists();

        if ($reservationsNonSoldees) {
            throw new SuppressionCompteBloqueeException(
                "Impossible de supprimer ce compte : une réservation est encore en cours ou un remboursement est en attente. Veuillez attendre qu'ils soient terminés."
            );
        }

        $gainsNonVerses = Gains::where('id_coiffeur', $utilisateur->id_user_app)
            ->whereIn('statut', ['en_attente', 'disponible'])
            ->exists();

        if ($gainsNonVerses) {
            throw new SuppressionCompteBloqueeException(
                "Impossible de supprimer ce compte : des gains ne lui ont pas encore été versés. Organisez leur versement avant de supprimer le compte."
            );
        }
    }

    private function supprimerFichierPhoto(string $photo): void
    {
        $nomFichier = basename($photo);
        $cheminComplet = public_path('images/utilisateurs/photos/' . $nomFichier);

        if (file_exists($cheminComplet)) {
            unlink($cheminComplet);
        }
    }
}
