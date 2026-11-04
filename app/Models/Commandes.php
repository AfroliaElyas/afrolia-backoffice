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
        'statut_remboursement',
        'remboursement_historique',
        'remboursement_reference',
    ];

    protected $table = 'commandes';

    protected $primaryKey = 'id_commande';

    // Sans ce cast, une colonne decimal remonte en chaîne de caractères en
    // JSON sous MySQL (contrairement à SQLite), ce qui casserait le parsing
    // côté Flutter (champ typé num).
    protected $casts = [
        'montant_produits' => 'float',
        'montant_commission' => 'float',
        'montant_total' => 'float',
    ];

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

    /**
     * Ajoute une entrée à l'historique de remboursement sans écraser les
     * précédentes — même principe que Reservations::ajouterHistoriqueRemboursement.
     */
    public function ajouterHistoriqueRemboursement(string $action, ?string $detail = null): void
    {
        $historique = json_decode($this->remboursement_historique ?? '[]', true) ?: [];
        $historique[] = array_filter([
            'au' => now()->toIso8601String(),
            'action' => $action,
            'detail' => $detail,
        ], fn ($valeur) => $valeur !== null);

        $this->update(['remboursement_historique' => json_encode($historique)]);
    }
}
