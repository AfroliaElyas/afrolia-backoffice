<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Paiements extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'id_reservation',
        'id_commande',
        'payment_intent_id',
        'amount',
        'currency',
        'payment_method',
        'provider_transaction_id',
        'status',
        'failure_reason',
        'processed_at',
    ];

    protected $table = 'paiements';

    protected $primaryKey = 'id_paiement';

    protected $casts = [
        'amount' => 'float',
    ];

    // Relations
    // Pas de relation utilisateur() directe : la table paiements n'a pas de
    // colonne id_user_app, un paiement se rattache à un client via sa
    // réservation ou sa commande (voir reservation()/commande() ci-dessous).

    public function reservation()
    {
        return $this->belongsTo(Reservations::class, 'id_reservation');
    }

    public function commande()
    {
        return $this->belongsTo(Commandes::class, 'id_commande');
    }
}
