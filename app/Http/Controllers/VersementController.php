<?php

namespace App\Http\Controllers;

use App\Models\Gains;
use App\Models\UsersApp;
use App\Models\Versement;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Versement des gains aux coiffeuses, en deux étapes distinctes pour
 * qu'aucun transfert réel ne parte sans une action explicite et séparée
 * de l'admin :
 * - preparer() fige le montant dû et verrouille les gains concernés
 *   (id_versement posé), sans appeler Jèko.
 * - confirmer() est la seule méthode qui appelle réellement
 *   MobileMoneyGatewayInterface::verser() — jamais déclenchée
 *   automatiquement, jamais par un job planifié.
 */
class VersementController extends Controller
{
    public function __construct(
        private readonly MobileMoneyGatewayInterface $mobileMoneyGateway
    ) {
    }

    /**
     * Gains "en_attente" réellement prêts à être versés à une coiffeuse :
     * soit une prestation réalisée (réservation "terminee"), soit une
     * pénalité (montant négatif, due immédiatement même si la réservation
     * liée a été annulée par la coiffeuse elle-même).
     */
    private function gainsEligibles(int $idCoiffeur)
    {
        return Gains::where('id_coiffeur', $idCoiffeur)
            ->where('statut', 'en_attente')
            ->whereNull('id_versement')
            ->whereNotNull('id_reservation')
            ->where(function ($q) {
                $q->where('montant_net', '<', 0)
                    ->orWhereHas('reservation', fn($r) => $r->where('statut', 'terminee'));
            });
    }

    /**
     * Renseigne le numéro Mobile Money de versement d'une coiffeuse,
     * directement depuis la page Versements (préalable obligatoire avant
     * de pouvoir confirmer un versement pour elle).
     */
    public function definirInfosVersement(Request $request, int $idCoiffeur)
    {
        $request->validate([
            'payout_telephone' => 'required|string|max:100',
            'payout_operateur' => 'required|string|in:wave,orange,mtn,moov,djamo',
        ], [
            'payout_telephone.required' => 'Veuillez saisir le numéro Mobile Money de la coiffeuse.',
            'payout_operateur.required' => 'Veuillez choisir l\'opérateur.',
        ]);

        $coiffeuse = UsersApp::where('role', 'hair')->findOrFail($idCoiffeur);
        $coiffeuse->update([
            'payout_telephone' => $request->payout_telephone,
            'payout_operateur' => $request->payout_operateur,
        ]);

        return back()->with('succes', 'Numéro de versement enregistré pour ' . $coiffeuse->name . ' ' . $coiffeuse->last_name . '.');
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $soldesParCoiffeuse = DB::table('gains')
            ->join('users_app', 'gains.id_coiffeur', '=', 'users_app.id_user_app')
            ->leftJoin('reservations', 'gains.id_reservation', '=', 'reservations.id_reservation')
            ->where('gains.statut', 'en_attente')
            ->whereNull('gains.id_versement')
            ->whereNotNull('gains.id_reservation')
            ->where(function ($q) {
                $q->where('gains.montant_net', '<', 0)
                    ->orWhere('reservations.statut', 'terminee');
            })
            ->groupBy(
                'users_app.id_user_app',
                'users_app.name',
                'users_app.last_name',
                'users_app.phone',
                'users_app.photo',
                'users_app.payout_telephone',
                'users_app.payout_operateur'
            )
            ->select(
                'users_app.id_user_app',
                'users_app.name',
                'users_app.last_name',
                'users_app.phone',
                'users_app.photo',
                'users_app.payout_telephone',
                'users_app.payout_operateur',
                DB::raw('SUM(gains.montant_net) as solde')
            )
            ->having('solde', '<>', 0)
            ->orderByDesc('solde')
            ->get();

        // Versements déjà préparés (a_valider) : exclus de la liste
        // ci-dessus pour ne pas proposer d'en préparer un deuxième en
        // double tant que le premier n'est pas confirmé ou annulé.
        $enAttenteDeConfirmation = Versement::where('statut', 'a_valider')->pluck('id_coiffeur')->all();
        $soldesParCoiffeuse = $soldesParCoiffeuse->reject(
            fn($s) => in_array($s->id_user_app, $enAttenteDeConfirmation, true)
        );

        $versementsAValider = Versement::with('coiffeur')
            ->where('statut', 'a_valider')
            ->orderByDesc('created_at')
            ->get();

        $historique = Versement::with('coiffeur', 'declencheur')
            ->whereIn('statut', ['reussi', 'echoue', 'annule'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return view('publicites.versements', compact('soldesParCoiffeuse', 'versementsAValider', 'historique'));
    }

    /**
     * Fige le montant dû et verrouille les gains concernés. N'appelle pas
     * Jèko : aucun argent ne bouge à cette étape.
     */
    public function preparer(int $idCoiffeur)
    {
        return DB::transaction(function () use ($idCoiffeur) {
            $coiffeuse = UsersApp::whereKey($idCoiffeur)->lockForUpdate()->first();

            if (!$coiffeuse) {
                return back()->withErrors(['Coiffeuse introuvable.']);
            }

            $dejaEnCours = Versement::where('id_coiffeur', $idCoiffeur)
                ->whereIn('statut', ['a_valider', 'en_cours'])
                ->exists();

            if ($dejaEnCours) {
                return back()->withErrors(['Un versement est déjà en attente pour cette coiffeuse.']);
            }

            $gains = $this->gainsEligibles($idCoiffeur)->lockForUpdate()->get();
            $montant = (float) $gains->sum('montant_net');

            if ($montant <= 0) {
                return back()->withErrors(['Aucun gain à verser pour cette coiffeuse.']);
            }

            $versement = Versement::create([
                'id_coiffeur' => $idCoiffeur,
                'montant' => $montant,
                'statut' => 'a_valider',
                'reference' => 'VERS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8)),
                'declenche_par' => Auth::id(),
            ]);

            Gains::whereIn('id_gain', $gains->pluck('id_gain'))->update(['id_versement' => $versement->id_versement]);

            return back()->with('succes', "Versement de {$montant} FCFA préparé pour {$coiffeuse->name} {$coiffeuse->last_name}. Vérifiez puis confirmez pour l'envoyer réellement.");
        });
    }

    /**
     * Seule méthode qui appelle réellement Jèko pour envoyer l'argent.
     * Comme pour RemboursementController::traiter(), le transfert Jèko est
     * asynchrone (statut initial "pending") : on considère ici qu'une
     * réponse autre que "error" vaut acceptation, un échec détecté après
     * coup par webhook n'est pas encore reconnecté à ce versement — même
     * limite déjà acceptée pour les remboursements.
     */
    public function confirmer(int $id)
    {
        $versement = Versement::with('coiffeur')->find($id);

        if (!$versement) {
            return back()->withErrors(['Versement introuvable.']);
        }

        if ($versement->statut !== 'a_valider') {
            return back()->withErrors(['Ce versement a déjà été traité.']);
        }

        $coiffeuse = $versement->coiffeur;

        if (!$coiffeuse || !$coiffeuse->payout_telephone || !$coiffeuse->payout_operateur) {
            return back()->withErrors([
                'Cette coiffeuse n\'a pas de numéro Mobile Money de versement renseigné. Complétez-le sur sa fiche avant de confirmer.',
            ]);
        }

        // Marquer "en_cours" avant l'appel réseau : si deux confirmations
        // partent en même temps, la seconde ne passe pas ce verrou.
        $verrouille = DB::table('versements')
            ->where('id_versement', $id)
            ->where('statut', 'a_valider')
            ->update(['statut' => 'en_cours', 'updated_at' => now()]);

        if (!$verrouille) {
            return back()->withErrors(['Ce versement vient d\'être traité par ailleurs.']);
        }

        try {
            $resultat = $this->mobileMoneyGateway->verser(
                destinataire: [
                    'nom' => trim("{$coiffeuse->name} {$coiffeuse->last_name}") ?: 'Coiffeuse Afrolia',
                    'telephone' => $coiffeuse->payout_telephone,
                    'operateur' => $coiffeuse->payout_operateur,
                ],
                montant: (float) $versement->montant,
                reference: $versement->reference
            );

            if (($resultat['status'] ?? null) === 'error') {
                throw new \RuntimeException('Jèko a refusé le transfert.');
            }
        } catch (\Throwable $e) {
            Log::error('Échec du versement Jèko', ['id_versement' => $id, 'erreur' => $e->getMessage()]);

            DB::transaction(function () use ($versement, $e) {
                $versement->update(['statut' => 'echoue', 'erreur' => $e->getMessage()]);
                // Les gains redeviennent éligibles pour une prochaine tentative.
                Gains::where('id_versement', $versement->id_versement)->update(['id_versement' => null]);
            });

            return back()->withErrors(['Le versement a échoué : ' . $e->getMessage()]);
        }

        $versement->update([
            'statut' => 'reussi',
            'id_transaction_jeko' => $resultat['id'] ?? null,
            'confirme_le' => now(),
        ]);

        Gains::where('id_versement', $versement->id_versement)->update([
            'statut' => 'paye',
            'date_paiement' => now(),
        ]);

        return back()->with('succes', 'Versement envoyé avec succès.');
    }

    /**
     * Annule un versement préparé mais pas encore confirmé : libère les
     * gains pour qu'ils puissent être inclus dans un prochain versement.
     */
    public function annuler(int $id)
    {
        return DB::transaction(function () use ($id) {
            $versement = Versement::whereKey($id)->lockForUpdate()->first();

            if (!$versement) {
                return back()->withErrors(['Versement introuvable.']);
            }

            if ($versement->statut !== 'a_valider') {
                return back()->withErrors(['Seul un versement pas encore confirmé peut être annulé.']);
            }

            Gains::where('id_versement', $id)->update(['id_versement' => null]);
            $versement->update(['statut' => 'annule']);

            return back()->with('succes', 'Versement annulé.');
        });
    }
}
