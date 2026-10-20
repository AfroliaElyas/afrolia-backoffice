<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie entre une cliente et sa coiffeuse autour d'une réservation
 * (échanger le lieu précis, un numéro, un détail de dernière minute) :
 * demandé le 20/10/2026 en complément du numéro de téléphone déjà affiché
 * sur l'écran de réservation côté coiffeuse.
 *
 * Rattachée à une réservation plutôt qu'à une paire cliente/coiffeuse
 * libre : un rendez-vous précis donne un contexte clair à la conversation
 * et une frontière naturelle d'autorisation (seules les deux parties de
 * cette réservation peuvent lire/écrire).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id('id_message');
            $table->unsignedBigInteger('id_reservation');
            $table->unsignedBigInteger('id_expediteur');
            $table->text('message');
            $table->boolean('lu')->default(false);
            $table->foreign('id_reservation')->references('id_reservation')->on('reservations')->onDelete('cascade');
            $table->foreign('id_expediteur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
