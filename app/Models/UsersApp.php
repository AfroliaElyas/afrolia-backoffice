<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class UsersApp extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'photo',
        'name',
        'last_name',
        'phone',
        'email',
        'presentation',
        'commune',
        'adresse',
        'experience',
        'password',
        'role',
        'otp',
        'statut',
        'formule_abonnement',
        'prochain_prelevement_le',
    ];

    protected $table = 'users_app';

    protected $primaryKey = 'id_user_app';

    protected $casts = [
        'prochain_prelevement_le' => 'datetime',
    ];

    public function reservationsClient()
    {
        return $this->hasMany(Reservations::class, 'id_client', 'id_user_app');
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Reviews::class, 'id_client', 'id_user_app');
    }

    public function favoriteStylists()
    {
        return $this->belongsToMany(
            UsersApp::class,
            'client_favorites',
            'client_id',
            'stylist_id'
        )->withTimestamps();
    }


    // ── Côté coiffeuse ──────────────────────────────────────────────

    public function reservationsCoiffeur()
    {
        return $this->hasMany(Reservations::class, 'id_coiffeur', 'id_user_app');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Reviews::class, 'id_stylist', 'id_user_app');
    }

    public function favoritedByClients()
    {
        return $this->belongsToMany(
            UsersApp::class,
            'client_favorites',
            'stylist_id',
            'client_id'
        )->withTimestamps();
    }

    public function gains()
    {
        return $this->hasMany(Gains::class, 'id_coiffeur', 'id_user_app');
    }

    public function abonnementPaiements()
    {
        return $this->hasMany(AbonnementPaiement::class, 'id_coiffeur', 'id_user_app');
    }

    public function servicesProposes()
    {
        return $this->hasMany(Services::class, 'id_utilisateur', 'id_user_app');
    }

    public function disponibilites()
    {
        return $this->hasMany(Disponibilites::class, 'id_utilisateur', 'id_user_app');
    }

    public function sociaux()
    {
        return $this->hasOne(Sociaux::class, 'id_utilisateur', 'id_user_app');
    }

    public function gallery()
    {
        return $this->hasMany(Gallery::class, 'id_utilisateur', 'id_user_app');
    }

    public function specialites()
    {
        return $this->belongsToMany(
            Specialites::class,
            'users_app_specialites',
            'id_utilisateur',
            'id_speciale'
        )->withTimestamps();
    }

    public function langues()
    {
        return $this->belongsToMany(
            Langues::class,
            'users_app_langues_parlees',
            'id_utilisateur',
            'id_language'
        )->withTimestamps();
    }

    public function methodesPaiement()
    {
        return $this->belongsToMany(
            MethodePaiement::class,
            'users_app_methode_paiement',
            'id_utilisateur',
            'id_methode'
        )->withTimestamps();
    }
}
