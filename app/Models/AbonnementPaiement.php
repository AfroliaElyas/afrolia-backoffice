<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbonnementPaiement extends Model
{
    protected $table = 'abonnement_paiements';

    protected $primaryKey = 'id_abonnement_paiement';

    protected $fillable = [
        'id_coiffeur',
        'formule',
        'montant',
        'statut',
        'date_prelevement',
    ];

    protected $casts = [
        'montant' => 'float',
        'date_prelevement' => 'datetime',
    ];

    public function coiffeuse()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur', 'id_user_app');
    }
}
