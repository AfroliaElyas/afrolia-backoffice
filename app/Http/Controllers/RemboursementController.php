<?php

namespace App\Http\Controllers;

use App\Models\Paiements;
use App\Models\Reservations;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemboursementController extends Controller
{
    public function __construct(
        private readonly StripeGatewayInterface $stripeGateway,
        private readonly MobileMoneyGatewayInterface $mobileMoneyGateway
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        // statut_remboursement distingue maintenant explicitement les 4
        // états (à traiter / en cours / remboursé / rejeté) au lieu de
        // déduire implicitement "à traiter" de statut=annulee +
        // statut_paiement=paye, qui ne permettait pas de voir si un
        // dossier avait déjà été rejeté ou était en cours de traitement
        // (demandé le 18/10/2026).
        $remboursements = DB::table('reservations')
            ->join('users_app as clients', 'reservations.id_client', '=', 'clients.id_user_app')
            ->join('users_app as coiffeuses', 'reservations.id_coiffeur', '=', 'coiffeuses.id_user_app')
            ->whereIn('reservations.statut_remboursement', ['en_attente', 'en_cours', 'traite', 'rejete'])
            ->select(
                'reservations.*',
                'clients.name as client_prenom',
                'clients.last_name as client_nom',
                'clients.phone as client_phone',
                'clients.photo as client_photo',
                'coiffeuses.name as coiffeuse_prenom',
                'coiffeuses.last_name as coiffeuse_nom',
                'coiffeuses.phone as coiffeuse_phone',
                'coiffeuses.photo as coiffeuse_photo'
            )
            ->orderByDesc('reservations.annule_le')
            ->get();

        $attente = $remboursements->where('statut_remboursement', 'en_attente')->count();
        $enCours = $remboursements->where('statut_remboursement', 'en_cours')->count();

        return view('publicites.remboursement', compact('attente', 'enCours', 'remboursements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Traiter un remboursement : rembourse réellement le paiement auprès du
     * prestataire avant de marquer la réservation comme remboursée — ne
     * jamais se contenter de changer le statut en base sans que l'argent
     * soit réellement retourné au client.
     *
     * Protection contre le double remboursement (deux clics simultanés,
     * deux administrateurs) : la "réclamation" du dossier (passage à
     * "en_cours") se fait dans une transaction avec verrou (lockForUpdate),
     * avant tout appel réseau — un deuxième appel concurrent qui arriverait
     * pendant l'appel à Stripe/Jèko trouve alors le dossier déjà "en_cours"
     * et s'arrête immédiatement, sans jamais effectuer de deuxième appel
     * réel. Le verrou n'est PAS gardé pendant l'appel réseau lui-même (qui
     * peut être lent) : seule la petite transaction de réclamation le tient
     * (demandé le 18/10/2026, suite à l'absence de ce verrou).
     *
     * Comportement après échec : le dossier redescend en "en_attente" (pas
     * de retour à "aucun"), donc une nouvelle tentative le retrouve dans la
     * liste à traiter. Pour Stripe, une clé d'idempotence stable (dérivée
     * de l'id du paiement) protège aussi contre le cas où l'appel précédent
     * a réellement atteint Stripe mais où seule la réponse s'est perdue :
     * Stripe renvoie alors le remboursement déjà créé au lieu d'en créer un
     * second. Pour Mobile Money, la référence envoyée à Jèko est stable
     * (dérivée de l'id du paiement, plus de suffixe aléatoire) pour la même
     * raison, côté Jèko.
     *
     * Limite connue pour Mobile Money : le transfert Jèko est asynchrone
     * (statut initial "pending", confirmé success/error plus tard par
     * webhook). On marque ici "rembourse" dès que Jèko accepte la demande
     * (pas d'erreur immédiate) ; un échec détecté après coup par le webhook
     * de transfert n'est pas encore reconnecté à cette réservation/ce
     * paiement — à corriger si ce cas se présente en pratique.
     */
    public function traiter(string $id)
    {
        $admin = Auth::user();

        // Réclame le dossier avant tout appel réseau : si un autre appel
        // (double clic, deux admins) a déjà réclamé ou traité ce dossier
        // entre-temps, celui-ci s'arrête ici sans jamais appeler Stripe/Jèko.
        $reservation = DB::transaction(function () use ($id) {
            $reservation = Reservations::whereKey($id)->lockForUpdate()->first();

            if (!$reservation || $reservation->statut_remboursement !== 'en_attente') {
                return null;
            }

            $reservation->update(['statut_remboursement' => 'en_cours']);

            return $reservation;
        });

        if (!$reservation) {
            return back()->withErrors([
                'Ce dossier n\'est plus "à traiter" (déjà en cours, déjà remboursé ou rejeté par quelqu\'un d\'autre) : aucune action effectuée.',
            ]);
        }

        $reservation->ajouterHistoriqueRemboursement('traitement_demarre', $admin ? "par {$admin->name}" : null);

        $paiement = Paiements::where('id_reservation', $id)
            ->where('status', 'succeeded')
            ->latest('id_paiement')
            ->first();

        if (!$paiement) {
            return $this->remettreEnAttenteEtEchouer($reservation, 'Aucun paiement réussi trouvé pour cette réservation.');
        }

        // La commission Afrolia reste due en cas d'annulation : si c'est la
        // cliente qui annule, elle la supporte (remboursée du prix moins la
        // commission) ; si c'est la coiffeuse, la cliente est remboursée
        // intégralement.
        $montantARembourser = $reservation->annule_par === 'coiffeur'
            ? (float) $paiement->amount
            : (float) $paiement->amount - (float) $reservation->montant_commission;

        if ($paiement->payment_method === 'stripe') {
            if (!$paiement->payment_intent_id) {
                return $this->remettreEnAttenteEtEchouer($reservation, 'Ce paiement Stripe ne référence aucun PaymentIntent, remboursement impossible automatiquement.');
            }

            try {
                $this->stripeGateway->refund(
                    $paiement->payment_intent_id,
                    $montantARembourser,
                    idempotencyKey: "remboursement-paiement-{$paiement->id_paiement}"
                );
            } catch (\Throwable $e) {
                Log::error('Échec du remboursement Stripe', ['id_paiement' => $paiement->id_paiement, 'erreur' => $e->getMessage()]);

                return $this->remettreEnAttenteEtEchouer($reservation, 'Le remboursement Stripe a échoué : ' . $e->getMessage(), $e->getMessage());
            }
        } elseif ($paiement->payment_method === 'mobile_money') {
            if (!$paiement->mobile_money_telephone || !$paiement->mobile_money_operateur) {
                return $this->remettreEnAttenteEtEchouer($reservation, 'Ce paiement Mobile Money ne précise pas de numéro/opérateur d\'origine, remboursement automatique impossible.');
            }

            $client = DB::table('users_app')->where('id_user_app', $reservation->id_client)->first();
            $nomClient = trim(($client->name ?? '') . ' ' . ($client->last_name ?? '')) ?: 'Client Afrolia';

            try {
                $resultat = $this->mobileMoneyGateway->refund(
                    destinataire: [
                        'nom' => $nomClient,
                        'telephone' => $paiement->mobile_money_telephone,
                        'operateur' => $paiement->mobile_money_operateur,
                    ],
                    montant: $montantARembourser,
                    // Stable (pas de suffixe aléatoire) : une nouvelle
                    // tentative après un échec ambigu réutilise la même
                    // référence, pour que Jèko puisse la reconnaître plutôt
                    // que de traiter un deuxième transfert distinct.
                    reference: "REMB-PAIEMENT-{$paiement->id_paiement}"
                );

                if (($resultat['status'] ?? null) === 'error') {
                    throw new \RuntimeException('Jèko a refusé le transfert de remboursement.');
                }
            } catch (\Throwable $e) {
                Log::error('Échec du remboursement Mobile Money', ['id_paiement' => $paiement->id_paiement, 'erreur' => $e->getMessage()]);

                return $this->remettreEnAttenteEtEchouer($reservation, 'Le remboursement Mobile Money a échoué : ' . $e->getMessage(), $e->getMessage());
            }
        } else {
            return $this->remettreEnAttenteEtEchouer($reservation, 'Moyen de paiement non pris en charge pour un remboursement automatique.');
        }

        DB::transaction(function () use ($id, $reservation) {
            Reservations::whereKey($id)->lockForUpdate()->update([
                'statut_paiement' => 'rembourse',
                'statut_remboursement' => 'traite',
            ]);
        });

        DB::table('paiements')
            ->where('id_paiement', $paiement->id_paiement)
            ->update([
                'status'       => 'refunded',
                'processed_at' => now(),
            ]);

        $reservation->ajouterHistoriqueRemboursement('rembourse', "Montant : {$montantARembourser} FCFA");

        return back()->with('succes', 'Le remboursement a été traité et le client a été remboursé.');
    }

    /**
     * Remet un dossier en "en_attente" après un échec (jamais "rejete" : un
     * échec technique n'est pas une décision de refuser un remboursement
     * dû), journalise la raison, et renvoie l'erreur à afficher.
     */
    private function remettreEnAttenteEtEchouer(Reservations $reservation, string $messageErreur, ?string $detailLog = null)
    {
        $reservation->update(['statut_remboursement' => 'en_attente']);
        $reservation->ajouterHistoriqueRemboursement('echec', $detailLog ?? $messageErreur);

        return back()->withErrors([$messageErreur]);
    }

    /**
     * Rejeter une demande de remboursement.
     *
     * IMPORTANT — ce que "rejeter" ne doit jamais servir à faire : les CGU
     * (§7) font du remboursement un droit automatique selon qui annule
     * (intégral si la coiffeuse annule, diminué des frais si la cliente
     * annule), pas une décision au cas par cas. "Rejeter" n'est donc PAS un
     * moyen de refuser un remboursement par ailleurs dû selon les CGU — à
     * réserver aux cas qui sortent de ce cadre (fraude avérée, doublon,
     * erreur de saisie, remboursement déjà effectué par un autre canal...).
     * Le code ne peut pas juger lui-même si un motif donné respecte cette
     * règle : la raison saisie reste obligatoire et horodatée dans
     * l'historique pour que ce soit vérifiable après coup, mais la
     * responsabilité du bien-fondé du rejet reste humaine (demandé le
     * 18/10/2026).
     *
     * Ne peut s'appliquer qu'à un dossier "en_attente" : impossible de
     * rejeter un dossier déjà remboursé (incohérent) ou déjà rejeté
     * (écraserait le motif précédent sans laisser de trace dans
     * l'historique).
     */
    public function rejeter(Request $request, string $id)
    {
        $request->validate([
            'raison' => 'required|string|max:500',
        ], [
            'raison.required' => 'Veuillez indiquer la raison du rejet.',
        ]);

        $admin = Auth::user();

        $reservation = DB::transaction(function () use ($id) {
            $reservation = Reservations::whereKey($id)->lockForUpdate()->first();

            if (!$reservation || $reservation->statut_remboursement !== 'en_attente') {
                return null;
            }

            $reservation->update(['statut_remboursement' => 'en_cours']);

            return $reservation;
        });

        if (!$reservation) {
            return back()->withErrors([
                'Ce dossier n\'est plus "à traiter" (déjà en cours, déjà remboursé ou déjà rejeté) : aucune action effectuée.',
            ]);
        }

        $reservation->update([
            'statut_remboursement' => 'rejete',
            'raison_rejet_remboursement' => $request->raison,
        ]);
        $reservation->ajouterHistoriqueRemboursement(
            'rejete',
            ($admin ? "par {$admin->name} : " : '') . $request->raison
        );

        return back()->with('succes', 'La demande de remboursement a été rejetée.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
