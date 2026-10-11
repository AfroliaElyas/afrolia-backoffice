<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Versement extends Model
{
    protected $table = 'versements';

    protected $primaryKey = 'id_versement';

    protected $fillable = [
        'id_coiffeur',
        'montant',
        'frais_jeko',
        'statut',
        'reference',
        'id_transaction_jeko',
        'erreur',
        'declenche_par',
        'confirme_le',
    ];

    protected $casts = [
        'montant' => 'float',
        'frais_jeko' => 'float',
        'confirme_le' => 'datetime',
    ];

    public function coiffeur()
    {
        return $this->belongsTo(UsersApp::class, 'id_coiffeur');
    }

    public function gains()
    {
        return $this->hasMany(Gains::class, 'id_versement');
    }

    public function declencheur()
    {
        return $this->belongsTo(User::class, 'declenche_par');
    }
}
