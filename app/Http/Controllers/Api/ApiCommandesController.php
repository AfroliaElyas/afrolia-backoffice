<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandeLignes;
use App\Models\Commandes;
use App\Models\Gains;
use App\Models\Produits;
use App\Models\UsersApp;
use App\Services\AbonnementService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiCommandesController extends Controller
{
    public function __construct(
        private readonly AbonnementService $abonnements,
        private readonly NotificationService $notifications
    ) {
    }

    // ✅ 1. Passer commande (checkout) : réserve le stock avant paiement
    public function store(Request $request)
    {
        $rules = [
            'id_coiffeur' => 'required|integer|exists:users_app,id_user_app',
            'methode_paiement' => 'required|string|in:stripe,mobile_money',
            'lignes' => 'required|array|min:1',
            'lignes.*.id_produit' => 'required|integer|exists:produits,id_produit',
            'lignes.*.quantite' => 'required|integer|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        // La commande est toujours passée pour l'utilisateur authentifié,
        // jamais pour un id_client transmis par le client.
        $idClient = $request->user()->id_user_app;

        try {
            $commande = DB::transaction(function () use ($request, $idClient) {
                // Verrouille les produits dans un ordre stable pour éviter les deadlocks
                // entre deux commandes concurrentes.
                $idsProduits = collect($request->lignes)->pluck('id_produit')->unique()->sort()->values();

                $produits = Produits::whereIn('id_produit', $idsProduits)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_produit');

                $montantProduits = 0;
                $lignesACreer = [];

                foreach ($request->lignes as $ligne) {
                    $produit = $produits->get($ligne['id_produit']);

                    if (!$produit || $produit->id_coiffeur != $request->id_coiffeur) {
                        throw ValidationException::withMessages([
                            'lignes' => "Le produit #{$ligne['id_produit']} n'appartient pas à cette coiffeuse.",
                        ]);
                    }

                    if ($produit->quantite_stock < $ligne['quantite']) {
                        throw ValidationException::withMessages([
                            'lignes' => "Stock insuffisant pour \"{$produit->nom}\".",
                        ]);
                    }

                    $produit->decrement('quantite_stock', $ligne['quantite']);

                    $sousTotal = $produit->prix * $ligne['quantite'];
                    $montantProduits += $sousTotal;

                    $lignesACreer[] = [
                        'id_produit' => $produit->id_produit,
                        'nom_produit' => $produit->nom,
                        'prix_unitaire' => $produit->prix,
                        'quantite' => $ligne['quantite'],
                        'sous_total' => $sousTotal,
                    ];
                }

                $coiffeuse = UsersApp::find($request->id_coiffeur);
                $taux = $coiffeuse ? $this->abonnements->tauxCommissionBoutiquePourCoiffeuse($coiffeuse) : $this->abonnements->tauxCommissionBoutique('gratuit');

                // La cliente paie exactement le prix affiché des produits :
                // la commission n'est pas ajoutée à sa charge, elle est
                // retenue sur les gains de la vendeuse (même mécanisme que
                // les réservations, confirmé le 18/10/2026).
                $montantCommission = round($montantProduits * $taux, 2);
                $montantTotal = $montantProduits;

                do {
                    $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                } while (Commandes::where('numero_commande', $numeroCommande)->exists());

                $commande = Commandes::create([
                    'numero_commande' => $numeroCommande,
                    'id_client' => $idClient,
                    'id_coiffeur' => $request->id_coiffeur,
                    'montant_produits' => $montantProduits,
                    'montant_commission' => $montantCommission,
                    'montant_total' => $montantTotal,
                    'methode_paiement' => $request->methode_paiement,
                ]);

                foreach ($lignesACreer as $ligne) {
                    CommandeLignes::create($ligne + ['id_commande' => $commande->id_commande]);
                }

                return $commande->load('lignes');
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commande créée avec succès',
            'data' => $commande,
        ], 201);
    }

    // ✅ 2. Historique des commandes d'une cliente
    public function getCommandesByClient(Request $request, $id_client)
    {
        if ((int) $id_client !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres commandes',
            ], 403);
        }

        $commandes = Commandes::with('lignes', 'coiffeur:id_user_app,name,last_name,photo,commune')
            ->where('id_client', $id_client)
            ->orderBy('id_commande', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $commandes,
        ]);
    }

    // ✅ 3. Commandes reçues par une coiffeuse
    public function getCommandesByCoiffeuse(Request $request, $id_coiffeur)
    {
        if ((int) $id_coiffeur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez consulter que vos propres commandes',
            ], 403);
        }

        $commandes = Commandes::with('lignes', 'client:id_user_app,name,last_name,photo,phone')
            ->where('id_coiffeur', $id_coiffeur)
            ->orderBy('id_commande', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $commandes,
        ]);
    }

    // ✅ 4. La coiffeuse marque la commande comme expédiée
    public function expedier(Request $request, $id_commande)
    {
        $commande = Commandes::where('id_commande', $id_commande)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable ou non autorisée',
            ], 404);
        }

        if ($commande->statut_commande !== 'payee') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une commande payée peut être expédiée',
            ], 400);
        }

        $commande->update([
            'statut_commande' => 'expediee',
            'expediee_le' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande marquée comme expédiée',
            'data' => $commande,
        ]);
    }

    // ✅ 5. La coiffeuse marque la commande comme livrée
    public function livrer(Request $request, $id_commande)
    {
        $commande = Commandes::where('id_commande', $id_commande)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable ou non autorisée',
            ], 404);
        }

        if ($commande->statut_commande !== 'expediee') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une commande expédiée peut être marquée comme livrée',
            ], 400);
        }

        $commande->update([
            'statut_commande' => 'livree',
            'livree_le' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande marquée comme livrée',
            'data' => $commande,
        ]);
    }

    // ✅ 6. La cliente annule sa propre commande payée, pas encore expédiée
    public function annuler(Request $request, $id_commande)
    {
        $commande = Commandes::where('id_commande', $id_commande)
            ->where('id_client', $request->user()->id_user_app)
            ->first();

        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable ou non autorisée',
            ], 404);
        }

        return $this->ouvrirDossierRemboursement($commande, 'client', $request->input('raison'));
    }

    // 🚫 7. La coiffeuse refuse une commande reçue, payée, pas encore expédiée
    public function refuser(Request $request, $id_commande)
    {
        $commande = Commandes::where('id_commande', $id_commande)
            ->where('id_coiffeur', $request->user()->id_user_app)
            ->first();

        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable ou non autorisée',
            ], 404);
        }

        return $this->ouvrirDossierRemboursement($commande, 'coiffeur', $request->input('raison'));
    }

    /**
     * Annule une commande payée et ouvre son dossier de remboursement :
     * restitue le stock immédiatement (le produit redevient disponible
     * sans attendre que l'argent soit réellement rendu, même principe que
     * commandes:annuler-expirees pour les commandes jamais payées) et
     * annule le gain en attente de la coiffeuse. Le remboursement réel
     * (appel Stripe/Mobile Money) est un acte distinct, traité ensuite côté
     * back-office (voir RemboursementCommandeController), jamais déclenché
     * directement depuis l'app — même principe de sécurité que pour les
     * réservations (demandé le 04/11/2026).
     *
     * Contrairement aux réservations, aucune pénalité de commission n'est
     * appliquée à la coiffeuse quand c'est elle qui refuse : rien n'a
     * encore été expédié, et la commission boutique n'a jamais été ajoutée
     * à la charge de la cliente (voir AbonnementService::tauxCommissionBoutique).
     */
    private function ouvrirDossierRemboursement(Commandes $commande, string $annulePar, ?string $raison)
    {
        if ($commande->statut_commande !== 'payee') {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée (pas encore payée, déjà expédiée, livrée ou annulée).',
            ], 400);
        }

        DB::transaction(function () use ($commande, $annulePar, $raison) {
            $commande = Commandes::whereKey($commande->id_commande)->lockForUpdate()->first();

            if ($commande->statut_commande !== 'payee') {
                return;
            }

            $idsProduits = $commande->lignes()->pluck('id_produit')->unique()->sort()->values();
            $produits = Produits::whereIn('id_produit', $idsProduits)->lockForUpdate()->get()->keyBy('id_produit');

            foreach ($commande->lignes as $ligne) {
                $produits->get($ligne->id_produit)?->increment('quantite_stock', $ligne->quantite);
            }

            $commande->update([
                'statut_commande' => 'annulee',
                'raison_annulation' => $raison ?: 'Aucune raison spécifiée',
                'annulee_le' => now(),
                'statut_remboursement' => 'en_attente',
            ]);

            $gain = Gains::where('id_commande', $commande->id_commande)->first();
            $gain?->update(['montant_net' => 0, 'statut' => 'annulee']);

            $commande->ajouterHistoriqueRemboursement(
                'demande_creee',
                $annulePar === 'client' ? 'Annulation par la cliente' : 'Annulation par la coiffeuse'
            );
        });

        $commande->refresh();

        $destinataire = $annulePar === 'client' ? $commande->id_coiffeur : $commande->id_client;
        $libelle = $annulePar === 'client' ? 'La cliente a annulé sa commande.' : 'La coiffeuse a annulé votre commande.';

        $this->notifications->envoyerAUtilisateur(
            UsersApp::find($destinataire),
            'Commande annulée',
            $libelle,
            ['type' => 'commande', 'id_commande' => $commande->id_commande],
        );

        return response()->json([
            'success' => true,
            'message' => 'Commande annulée, remboursement en cours de traitement',
            'data' => $commande,
        ]);
    }
}
