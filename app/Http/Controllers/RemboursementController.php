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
            ->whereIn('reservations.statut_remboursement', ['en_attente', 'en_cours', 'incertain', 'traite', 'rejete'])
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
        $incertain = $remboursements->where('statut_remboursement', 'incertain')->count();

        return view('publicites.remboursement', compact('attente', 'enCours', 'incertain', 'remboursements'));
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

        // Stockée avant l'appel réseau : c'est par cette référence que le
        // webhook Jèko (confirmation ou échec du transfert) retrouvera ce
        // dossier après coup, y compris si la réponse synchrone de Jèko se
        // perd (voir ApiPaiementsController::traiterWebhookTransfertRemboursement).
        if ($paiement->payment_method === 'mobile_money') {
            $reservation->update(['remboursement_reference' => self::referenceTransfertJeko($paiement->id_paiement)]);
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
                    reference: self::referenceTransfertJeko($paiement->id_paiement)
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
     * Référence stable envoyée à Jèko pour le transfert de remboursement
     * d'un paiement donné — une seule définition, réutilisée à la fois
     * pour l'appel à Jèko et pour la colonne remboursement_reference, afin
     * qu'elles ne puissent jamais diverger (le webhook retrouve le dossier
     * par cette colonne, voir ApiPaiementsController::traiterWebhookTransfertRemboursement).
     */
    public static function referenceTransfertJeko(int $idPaiement): string
    {
        return "REMB-PAIEMENT-{$idPaiement}";
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
     * Motifs de rejet acceptés : tous situés HORS du droit automatique que
     * les CGU (§7) accordent à toute annulation d'une réservation déjà
     * payée (intégral si la coiffeuse annule, diminué des frais si la
     * cliente annule). Un commentaire et un motif libre obligatoire ne
     * suffisaient pas à empêcher un rejet d'un remboursement par ailleurs
     * dû (demandé le 18/10/2026) : la validation ci-dessous REFUSE
     * désormais toute catégorie qui ne figure pas dans cette liste fermée,
     * donc un rejet "ordinaire" (la cliente ou la coiffeuse a simplement
     * annulé) ne peut plus passer la validation, quel que soit le texte
     * saisi en détail. N'ajoutez une catégorie ici que si elle décrit
     * réellement un cas hors CGU.
     */
    private const MOTIFS_REJET_HORS_CGU = [
        'fraude_averee' => 'Fraude avérée sur cette réservation ou ce compte',
        'doublon' => 'Doublon : une autre demande concernant le même paiement est déjà traitée',
        'deja_rembourse_autre_canal' => 'Déjà remboursé par un autre canal (hors de l\'application)',
        'erreur_de_saisie' => 'Erreur de saisie empêchant d\'identifier la cliente ou la réservation',
    ];

    /**
     * Rejeter une demande de remboursement.
     *
     * Ne peut s'appliquer qu'à un dossier "en_attente" : impossible de
     * rejeter un dossier déjà remboursé (incohérent) ou déjà rejeté
     * (écraserait le motif précédent sans laisser de trace dans
     * l'historique).
     */
    public function rejeter(Request $request, string $id)
    {
        $request->validate([
            'motif' => 'required|in:' . implode(',', array_keys(self::MOTIFS_REJET_HORS_CGU)),
            'details' => 'required|string|max:500',
        ], [
            'motif.required' => 'Veuillez choisir un motif.',
            'motif.in' => 'Ce motif n\'est pas accepté : un remboursement dû selon les CGU ne peut pas être rejeté.',
            'details.required' => 'Veuillez préciser les détails de ce motif.',
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

        $libelleMotif = self::MOTIFS_REJET_HORS_CGU[$request->motif];
        $raisonComplete = "{$libelleMotif} — {$request->details}";

        $reservation->update([
            'statut_remboursement' => 'rejete',
            'raison_rejet_remboursement' => $raisonComplete,
        ]);
        $reservation->ajouterHistoriqueRemboursement(
            'rejete',
            ($admin ? "par {$admin->name} : " : '') . $raisonComplete
        );

        return back()->with('succes', 'La demande de remboursement a été rejetée.');
    }

    /**
     * Les 3 constats possibles pour un dossier "incertain". Un simple texte
     * obligatoire ne suffisait pas (première version) : il ramenait
     * systématiquement le dossier en "en_attente" quel que soit ce que le
     * texte disait, y compris si la vérification concluait que le
     * remboursement avait déjà eu lieu — ce qui aurait pu mener à un
     * deuxième remboursement réel (demandé le 18/10/2026). Le constat
     * choisi, pas le texte libre, détermine maintenant l'effet :
     * - rembourse_confirme : clôture directement le dossier comme
     *   remboursé (jamais de nouvelle tentative, puisque l'argent est déjà
     *   parti) ;
     * - non_effectue_confirme : autorise une nouvelle tentative
     *   ("en_attente") ;
     * - resultat_inconnu : ne change rien, le dossier reste "incertain" et
     *   bloqué — sert à journaliser une vérification faite mais non
     *   concluante (ex. transfert encore "pending" chez Jèko), sans
     *   prétendre à une conclusion qui n'existe pas encore.
     */
    private const CONSTATS_VERIFICATION = ['rembourse_confirme', 'non_effectue_confirme', 'resultat_inconnu'];

    /**
     * Seule sortie possible de l'état "incertain" (voir
     * DebloquerRemboursementsEnCours) : exige un constat explicite parmi
     * CONSTATS_VERIFICATION, jamais une simple confirmation en un clic.
     */
    public function confirmerVerification(Request $request, string $id)
    {
        $request->validate([
            'constat' => 'required|in:' . implode(',', self::CONSTATS_VERIFICATION),
            'preuve' => 'required|string|max:500',
        ], [
            'constat.required' => 'Choisissez ce que vous avez constaté.',
            'preuve.required' => 'Indiquez ce que vous avez vérifié (ex. : référence consultée sur le dashboard Stripe/Jèko, statut constaté).',
        ]);

        $admin = Auth::user();
        $constat = $request->constat;

        $reservation = DB::transaction(function () use ($id) {
            $reservation = Reservations::whereKey($id)->lockForUpdate()->first();

            if (!$reservation || $reservation->statut_remboursement !== 'incertain') {
                return null;
            }

            // Marque l'intention tout de suite, sous le même verrou que
            // traiter()/rejeter() : un autre appel concurrent sur ce
            // dossier (très improbable ici, mais le même principe que
            // partout ailleurs dans ce contrôleur) le trouve déjà "en_cours"
            // plutôt que de pouvoir agir en même temps.
            $reservation->update(['statut_remboursement' => 'en_cours']);

            return $reservation;
        });

        if (!$reservation) {
            return back()->withErrors([
                'Ce dossier n\'est plus "incertain" (déjà vérifié ou déjà traité par quelqu\'un d\'autre) : aucune action effectuée.',
            ]);
        }

        $prefixeAuteur = $admin ? "par {$admin->name} : " : '';

        match ($constat) {
            'rembourse_confirme' => (function () use ($reservation, $prefixeAuteur, $request) {
                Reservations::whereKey($reservation->id_reservation)->update([
                    'statut_paiement' => 'rembourse',
                    'statut_remboursement' => 'traite',
                ]);
                Paiements::where('id_reservation', $reservation->id_reservation)
                    ->where('status', 'succeeded')
                    ->update(['status' => 'refunded', 'processed_at' => now()]);
                $reservation->ajouterHistoriqueRemboursement(
                    'rembourse',
                    $prefixeAuteur . 'confirmé manuellement comme déjà effectué : ' . $request->preuve
                );
            })(),
            'non_effectue_confirme' => (function () use ($reservation, $prefixeAuteur, $request) {
                $reservation->update(['statut_remboursement' => 'en_attente']);
                $reservation->ajouterHistoriqueRemboursement(
                    'echec',
                    $prefixeAuteur . 'confirmé manuellement comme non effectué, nouvelle tentative autorisée : ' . $request->preuve
                );
            })(),
            'resultat_inconnu' => (function () use ($reservation, $prefixeAuteur, $request) {
                // Remis "incertain" : la ligne précédente l'a mis "en_cours"
                // le temps de ce traitement, mais aucune conclusion n'a été
                // établie — le dossier doit rester bloqué, pas redevenir
                // actionnable.
                $reservation->update(['statut_remboursement' => 'incertain']);
                $reservation->ajouterHistoriqueRemboursement(
                    'verification_non_concluante',
                    $prefixeAuteur . 'vérifié mais résultat encore inconnu, reste bloqué : ' . $request->preuve
                );
            })(),
        };

        $messages = [
            'rembourse_confirme' => 'Dossier clôturé comme remboursé.',
            'non_effectue_confirme' => 'Confirmé non effectué : une nouvelle tentative est maintenant possible.',
            'resultat_inconnu' => 'Enregistré : le dossier reste bloqué tant que le résultat n\'est pas connu.',
        ];

        return back()->with('succes', $messages[$constat]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
