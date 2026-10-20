<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Reservations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiMessagesController extends Controller
{
    /**
     * Vérifie que l'utilisateur authentifié est bien la cliente ou la
     * coiffeuse de cette réservation, seules autorisées à voir/écrire dans
     * sa messagerie. Retourne la réservation, ou null (avec la réponse
     * d'erreur déjà envoyée) si l'accès est refusé.
     */
    private function reservationAutorisee(Request $request, $id_reservation, ?array &$erreur): ?Reservations
    {
        $reservation = Reservations::find($id_reservation);

        if (!$reservation) {
            $erreur = ['message' => 'Réservation non trouvée', 'status' => 404];
            return null;
        }

        $userId = (int) $request->user()->id_user_app;
        if ((int) $reservation->id_client !== $userId && (int) $reservation->id_coiffeur !== $userId) {
            $erreur = ['message' => 'Réservation non trouvée', 'status' => 404];
            return null;
        }

        return $reservation;
    }

    // Liste des messages d'une réservation, et marque comme lus ceux reçus
    // (envoyés par l'autre partie) : la consultation vaut accusé de lecture.
    public function index(Request $request, $id_reservation)
    {
        $erreur = null;
        $reservation = $this->reservationAutorisee($request, $id_reservation, $erreur);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => $erreur['message']], $erreur['status']);
        }

        $userId = (int) $request->user()->id_user_app;

        Message::where('id_reservation', $id_reservation)
            ->where('id_expediteur', '!=', $userId)
            ->where('lu', false)
            ->update(['lu' => true]);

        $messages = Message::where('id_reservation', $id_reservation)
            ->orderBy('id_message')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    public function store(Request $request, $id_reservation)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $erreur = null;
        $reservation = $this->reservationAutorisee($request, $id_reservation, $erreur);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => $erreur['message']], $erreur['status']);
        }

        $message = Message::create([
            'id_reservation' => $reservation->id_reservation,
            'id_expediteur' => $request->user()->id_user_app,
            'message' => $request->input('message'),
            'lu' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé',
            'data' => $message,
        ], 201);
    }

    // Nombre total de messages non lus de l'utilisateur authentifié, tous
    // azimuts (en tant que cliente et/ou coiffeuse) : pour une pastille de
    // notification, sans avoir à interroger chaque réservation une par une.
    public function nonLus(Request $request)
    {
        $userId = (int) $request->user()->id_user_app;

        $count = Message::where('id_expediteur', '!=', $userId)
            ->where('lu', false)
            ->whereHas('reservation', function ($query) use ($userId) {
                $query->where('id_client', $userId)->orWhere('id_coiffeur', $userId);
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['non_lus' => $count],
        ]);
    }
}
