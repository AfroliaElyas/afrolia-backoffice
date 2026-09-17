<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandeLignes extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_commande',
        'id_produit',
        'nom_produit',
        'prix_unitaire',
        'quantite',
        'sous_total',
    ];

    protected $table = 'commande_lignes';

    protected $primaryKey = 'id_ligne';

    // Sans ce cast, une colonne decimal remonte en chaîne de caractères en
    // JSON sous MySQL (contrairement à SQLite), ce qui casserait le parsing
    // côté Flutter (champ typé num).
    protected $casts = [
        'prix_unitaire' => 'float',
        'sous_total' => 'float',
    ];

    public function commande()
    {
        return $this->belongsTo(Commandes::class, 'id_commande');
    }

    public function produit()
    {
        return $this->belongsTo(Produits::class, 'id_produit');
    }
}
