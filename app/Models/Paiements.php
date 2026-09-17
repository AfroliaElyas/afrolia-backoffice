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

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_user_app');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservations::class, 'id_reservation');
    }

    public function commande()
    {
        return $this->belongsTo(Commandes::class, 'id_commande');
    }
}
