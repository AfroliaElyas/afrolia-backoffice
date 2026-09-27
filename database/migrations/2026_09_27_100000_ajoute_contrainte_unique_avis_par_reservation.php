<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un seul avis par réservation. Garde-fou en base en complément du
 * contrôle applicatif dans ApiAvisController::store() — empêche toute
 * régression même si ce contrôle est un jour contourné/oublié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('id_reservation');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['id_reservation']);
        });
    }
};
