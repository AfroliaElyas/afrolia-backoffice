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
        'id_versement',
    ];

    protected $table = 'gains';

    protected $primaryKey = 'id_gain';

    protected $casts = [
        'montant_brut' => 'float',
        'montant_commission' => 'float',
        'montant_net' => 'float',
    ];

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiements::class, 'id_paiement');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservations::class, 'id_reservation');
    }

    public function commande()
    {
        return $this->belongsTo(Commandes::class, 'id_commande');
    }

    public function versement()
    {
        return $this->belongsTo(Versement::class, 'id_versement');
    }
}
