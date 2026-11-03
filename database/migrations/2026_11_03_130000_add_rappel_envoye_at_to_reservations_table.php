<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rappel de rendez-vous (notification push), demandé le 03/11/2026 :
 * cette colonne empêche d'envoyer le même rappel deux fois (la commande
 * planifiée rappels:envoyer ne sélectionne que les réservations où elle
 * est encore nulle), même si elle est relancée ou que deux exécutions
 * planifiées se chevauchent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('rappel_envoye_at')->nullable()->after('termine_le');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('rappel_envoye_at');
        });
    }
};
