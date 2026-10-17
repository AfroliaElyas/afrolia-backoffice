<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Models\Reservations;
use Illuminate\Http\Request;

class ApiLitigeController extends Controller
{
    /**
     * Signaler un litige sur une réservation. Accessible à la cliente
     * comme à la coiffeuse, dans la mesure où toutes deux peuvent être à
     * l'origine d'un désaccord (qualité de service, comportement,
     * annulation abusive...).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_reservation' => 'required|integer|exists:reservations,id_reservation',
            'type' => 'required|in:qualite_service,paiement,comportement,annulation_abusive,autre',
            'description' => 'required|string|max:2000',
        ]);

        $reservation = Reservations::find($validated['id_reservation']);
        $idUtilisateur = (int) $request->user()->id_user_app;

        // Le signalement n'est ouvert qu'aux deux parties réellement
        // concernées par la réservation citée, jamais à un tiers.
        if ($idUtilisateur !== (int) $reservation->id_client && $idUtilisateur !== (int) $reservation->id_coiffeur) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez signaler un litige que sur vos propres réservations',
            ], 403);
        }

        $litige = Litige::create([
            'id_reservation' => $reservation->id_reservation,
            // Toujours l'utilisateur authentifié, jamais une valeur transmise.
            'id_plaignant' => $idUtilisateur,
            'type' => $validated['type'],
            'description' => $validated['description'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre signalement a bien été transmis. Nous reviendrons vers vous après examen.',
            'data' => $litige,
        ], 201);
    }

    /**
     * Liste des signalements faits par l'utilisateur authentifié, pour
     * qu'il puisse suivre leur traitement dans l'application.
     */
    public function mesLitiges(Request $request)
    {
        $litiges = Litige::where('id_plaignant', $request->user()->id_user_app)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $litiges,
        ]);
    }
}
