<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produits;
use App\Models\UsersApp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiProduitsController extends Controller
{
    // ✅ 1. Catalogue global (produits actifs de toutes les coiffeuses)
    public function getCatalogue(Request $request)
    {
        $produits = Produits::with('coiffeur')
            ->where('statut', 'actif')
            ->orderBy('id_produit', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $produits,
        ]);
    }

    // ✅ 2. Produits d'une coiffeuse donnée
    public function getProduitsByCoiffeuse($id_coiffeur)
    {
        $userExists = UsersApp::where('id_user_app', $id_coiffeur)->exists();

        if (!$userExists) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé',
            ], 404);
        }

        $produits = Produits::where('id_coiffeur', $id_coiffeur)
            ->orderBy('id_produit', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $produits,
        ]);
    }

    // ✅ 3. Création d'un produit
    public function store(Request $request)
    {
        $rules = [
            'id_coiffeur' => 'required|integer|exists:users_app,id_user_app',
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string',
            'prix' => 'required|numeric|min:0',
            'quantite_stock' => 'required|integer|min:0',
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $file = $request->file('photo');
        $timestamp = Carbon::now()->format('Ymd_His');
        $photoName = 'produit_' . $timestamp . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('salon/produits'), $photoName);
        $photoUrl = url('afrolia/public/salon/produits/' . $photoName);

        $produit = Produits::create([
            'id_coiffeur' => $request->id_coiffeur,
            'nom' => $request->nom,
            'description' => $request->description,
            'prix' => $request->prix,
            'quantite_stock' => $request->quantite_stock,
            'photo' => $photoUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté avec succès',
            'data' => $produit,
        ], 201);
    }

    // ✅ 4. Mise à jour d'un produit
    public function update(Request $request, $id)
    {
        $produit = Produits::find($id);

        if (!$produit) {
            return response()->json([
                'success' => false,
                'message' => 'Produit introuvable',
            ], 404);
        }

        $rules = [
            'nom' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'prix' => 'sometimes|required|numeric|min:0',
            'quantite_stock' => 'sometimes|required|integer|min:0',
            'statut' => 'sometimes|required|in:actif,inactif',
            'photo' => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        if ($request->hasFile('photo')) {
            if ($produit->photo) {
                $anciennePhoto = public_path(parse_url($produit->photo, PHP_URL_PATH));
                if (file_exists($anciennePhoto)) {
                    @unlink($anciennePhoto);
                }
            }

            $file = $request->file('photo');
            $timestamp = Carbon::now()->format('Ymd_His');
            $photoName = 'produit_' . $timestamp . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('salon/produits'), $photoName);
            $produit->photo = url('afrolia/public/salon/produits/' . $photoName);
        }

        $produit->fill($request->only(['nom', 'description', 'prix', 'quantite_stock', 'statut']));
        $produit->save();

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => $produit,
        ]);
    }

    // ✅ 5. Suppression d'un produit
    public function destroy($id)
    {
        $produit = Produits::find($id);

        if (!$produit) {
            return response()->json([
                'success' => false,
                'message' => 'Produit introuvable',
            ], 404);
        }

        if ($produit->photo) {
            $photoPath = public_path(parse_url($produit->photo, PHP_URL_PATH));
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }

        $produit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès',
        ]);
    }
}
