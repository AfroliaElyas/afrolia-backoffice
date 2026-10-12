<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecoveryCode extends Model
{
    protected $fillable = [
        'id_user_app',
        'code_hash',
        'utilise_le',
        'utilise_ip',
        'utilise_user_agent',
    ];

    protected $casts = [
        'utilise_le' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_user_app');
    }
}
