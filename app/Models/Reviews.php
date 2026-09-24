<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Reviews extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'id_reservation',
        'id_client',
        'id_stylist',
        'rating',
        'comment',
        'is_anonymous',
        'is_verified',
        'status',
    ];

    protected $table = 'reviews';

    protected $primaryKey = 'id_review';

      // Relations
    public function client()
    {
        return $this->belongsTo(UsersApp::class, 'id_client');
    }

    public function coiffeuse()
    {
        return $this->belongsTo(UsersApp::class, 'id_stylist');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservations::class, 'id_reservation');
    }
}
