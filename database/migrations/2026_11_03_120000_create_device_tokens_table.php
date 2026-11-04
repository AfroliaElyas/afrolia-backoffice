<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons d'appareil (FCM) pour les notifications push, demandées le
 * 03/11/2026 (réservations, confirmations, annulations, rappels,
 * messages, paiements).
 *
 * Un jeton par ligne, jamais par utilisateur : un compte peut être
 * connecté sur plusieurs appareils (ancien et nouveau téléphone), et un
 * même jeton peut changer de propriétaire (téléphone revendu,
 * réinstallation) — la colonne token est donc unique, et l'enregistrement
 * se fait toujours par upsert sur ce jeton (voir ApiDeviceTokenController)
 * pour ne jamais le dupliquer ni le laisser associé au mauvais compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user_app');
            $table->string('token', 255)->unique();
            $table->string('plateforme', 20)->comment('android, ios');
            $table->foreign('id_user_app')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
