<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produits extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_coiffeur',
        'nom',
        'description',
        'prix',
        'quantite_stock',
        'photo',
        'statut',
    ];

    protected $table = 'produits';

    protected $primaryKey = 'id_produit';

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur', 'id_user_app');
    }
}
