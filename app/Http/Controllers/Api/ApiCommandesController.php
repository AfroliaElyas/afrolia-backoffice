<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandeLignes;
use App\Models\Commandes;
use App\Models\Produits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiCommandesController extends Controller
{
    // ✅ 1. Passer commande (checkout) : réserve le stock avant paiement
    public function store(Request $request)
    {
        $rules = [
            'id_client' => 'required|integer|exists:users_app,id_user_app',
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

        try {
            $commande = DB::transaction(function () use ($request) {
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

                $montantCommission = round($montantProduits * 0.15, 2);
                $montantTotal = $montantProduits + $montantCommission;

                do {
                    $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                } while (Commandes::where('numero_commande', $numeroCommande)->exists());

                $commande = Commandes::create([
                    'numero_commande' => $numeroCommande,
                    'id_client' => $request->id_client,
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
    public function getCommandesByClient($id_client)
    {
        $commandes = Commandes::with('lignes', 'coiffeur')
            ->where('id_client', $id_client)
            ->orderBy('id_commande', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $commandes,
        ]);
    }

    // ✅ 3. Commandes reçues par une coiffeuse
    public function getCommandesByCoiffeuse($id_coiffeur)
    {
        $commandes = Commandes::with('lignes', 'client')
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
            ->where('id_coiffeur', $request->id_coiffeur)
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
            ->where('id_coiffeur', $request->id_coiffeur)
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
}
