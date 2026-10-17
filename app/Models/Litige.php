<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Litige extends Model
{
    protected $table = 'litiges';
    protected $primaryKey = 'id_litige';

    protected $fillable = [
        'id_reservation',
        'id_plaignant',
        'type',
        'description',
    ];
}
