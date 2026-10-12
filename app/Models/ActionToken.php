<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionToken extends Model
{
    protected $fillable = [
        'id_user_app',
        'type',
        'token_hash',
        'expire_le',
        'utilise_le',
    ];

    protected $casts = [
        'expire_le' => 'datetime',
        'utilise_le' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_user_app');
    }
}
