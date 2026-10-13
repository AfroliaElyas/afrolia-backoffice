<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InscriptionLancement extends Model
{
    protected $table = 'inscriptions_lancement';
    protected $primaryKey = 'id_inscription_lancement';

    protected $fillable = [
        'email',
    ];
}
