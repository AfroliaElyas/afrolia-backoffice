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
        Schema::table('gains', function (Blueprint $table) {
            $table->unsignedBigInteger('id_reservation')->nullable()->change();
            $table->unsignedBigInteger('id_commande')->nullable()->after('id_reservation');
            $table->foreign('id_commande')->references('id_commande')->on('commandes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gains', function (Blueprint $table) {
            $table->dropForeign(['id_commande']);
            $table->dropColumn('id_commande');
            $table->unsignedBigInteger('id_reservation')->nullable(false)->change();
        });
    }
};
