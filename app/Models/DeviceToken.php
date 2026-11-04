<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'id_user_app',
        'token',
        'plateforme',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(UsersApp::class, 'id_user_app', 'id_user_app');
    }
}
