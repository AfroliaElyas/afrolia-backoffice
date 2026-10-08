<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comme prix_service/montant_commission/montant_total, la durée du service
 * doit être figée au moment de la réservation : si la coiffeuse modifie
 * ensuite la durée du service (champ services.minute), les réservations
 * déjà prises ne doivent pas changer de durée rétroactivement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('duree_minutes')->nullable()->after('heure_reservation');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('duree_minutes');
        });
    }
};
