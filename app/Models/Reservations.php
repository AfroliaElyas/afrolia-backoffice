<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Reservations extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'numero_reservation',
        'id_client',
        'id_coiffeur',
        'client_nom_facturation',
        'coiffeur_nom_facturation',
        'id_service',
        'date_reservation',
        'heure_reservation',
        'duree_minutes',
        'statut',
        'prix_service',
        'montant_commission',
        'montant_total',
        'statut_paiement',
        'statut_remboursement',
        'raison_rejet_remboursement',
        'remboursement_historique',
        'methode_paiement',
        'notes',
        'raison_annulation',
        'annule_par',
        'annule_le',
        'confirme_le',
        'termine_le',
    ];

    protected $table = 'reservations';

    protected $primaryKey = 'id_reservation';

    // Sans ce cast, une colonne decimal remonte en chaîne de caractères en
    // JSON sous MySQL (contrairement à SQLite), ce qui casserait le parsing
    // côté Flutter (le `as num` sur montant_total à la confirmation de
    // paiement). Même correctif que sur Produits::prix et Services::prix.
    protected $casts = [
        'prix_service' => 'float',
        'montant_commission' => 'float',
        'montant_total' => 'float',
    ];

    // Relations
    //
    // utilisateur() référençait auparavant une colonne id_user_app qui
    // n'existe pas sur cette table (seules id_client et id_coiffeur
    // existent) : tout appel aurait levé une erreur SQL. Remplacée par les
    // deux relations réellement utilisables.
    public function client()
    {
        return $this->belongsTo(UsersApp::class, 'id_client');
    }

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur');
    }

    public function service()
    {
        return $this->belongsTo(Services::class, 'id_service');
    }

    /**
     * Ajoute une entrée à l'historique de remboursement (demande, tentative,
     * échec, succès, rejet...) sans jamais écraser les précédentes :
     * nécessaire pour que l'équipe puisse suivre un dossier dans le temps,
     * pas seulement voir son état actuel (demandé le 18/10/2026).
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
