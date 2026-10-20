<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';

    protected $primaryKey = 'id_message';

    protected $fillable = [
        'id_reservation',
        'id_expediteur',
        'message',
        'lu',
    ];

    protected $casts = [
        'lu' => 'boolean',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservations::class, 'id_reservation');
    }

    public function expediteur()
    {
        return $this->belongsTo(UsersApp::class, 'id_expediteur', 'id_user_app');
    }
}
