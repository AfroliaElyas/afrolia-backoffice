<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Renseignés uniquement quand le compte client/coiffeuse est
            // supprimé (ses données personnelles sont alors effacées) :
            // conservent l'identité telle qu'elle était au moment de la
            // suppression, pour les pièces justificatives qui doivent
            // continuer à l'indiquer malgré la suppression du compte.
            $table->string('client_nom_facturation')->nullable()->after('id_client');
            $table->string('coiffeur_nom_facturation')->nullable()->after('id_coiffeur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['client_nom_facturation', 'coiffeur_nom_facturation']);
        });
    }
};
