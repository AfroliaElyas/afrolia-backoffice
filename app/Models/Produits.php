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

    // Sans ce cast, une colonne decimal remonte en chaîne de caractères en
    // JSON sous MySQL (contrairement à SQLite), ce qui casserait le parsing
    // côté Flutter (champ typé num).
    protected $casts = [
        'prix' => 'float',
    ];

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur', 'id_user_app');
    }
}
