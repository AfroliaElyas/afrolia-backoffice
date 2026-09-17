<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Gains extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'id_coiffeur',
        'id_reservation',
        'id_commande',
        'montant_brut',
        'montant_commission',
        'montant_net',
        'statut',
        'date_paiement',
    ];

    protected $table = 'gains';

    protected $primaryKey = 'id_gain';

    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_user_app');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiements::class, 'id_paiement');
    }

    public function commande()
    {
        return $this->belongsTo(Commandes::class, 'id_commande');
    }
}
