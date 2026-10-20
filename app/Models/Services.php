<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Services extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prix',
        'minute',
        'description',
        'longueur',
        'meches_comprises',
        'id_utilisateur',
        'id_speciale',
    ];

    protected $table = 'services';

    protected $primaryKey = 'id_service';

    // Sans ce cast, une colonne decimal remonte en chaîne de caractères en
    // JSON sous MySQL (contrairement à SQLite), ce qui casserait le parsing
    // côté Flutter (champs typés num). Même correctif que sur Produits::prix.
    protected $casts = [
        'prix' => 'float',
        'meches_comprises' => 'boolean',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_utilisateur', 'id_user_app');
    }

    public function specialite()
    {
        return $this->belongsTo(Specialites::class, 'id_speciale', 'id_specialite');
    }
}
