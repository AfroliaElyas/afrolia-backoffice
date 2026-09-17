<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commandes extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_commande',
        'id_client',
        'id_coiffeur',
        'montant_produits',
        'montant_commission',
        'montant_total',
        'methode_paiement',
        'statut_paiement',
        'statut_commande',
        'raison_annulation',
        'expediee_le',
        'livree_le',
        'annulee_le',
    ];

    protected $table = 'commandes';

    protected $primaryKey = 'id_commande';

    public function client()
    {
        return $this->belongsTo(UsersApp::class, 'id_client', 'id_user_app');
    }

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur', 'id_user_app');
    }

    public function lignes()
    {
        return $this->hasMany(CommandeLignes::class, 'id_commande');
    }
}
