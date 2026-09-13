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
        Schema::create('users_app_methode_paiement', function (Blueprint $table) {
            $table->id('id_users_app_methode_paiement');
            $table->unsignedBigInteger('id_methode');
            $table->foreign('id_methode')->references('id_methode_paiement')->on('methode_paiement')->onDelete('cascade');
            $table->unsignedBigInteger('id_utilisateur');
            $table->foreign('id_utilisateur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_app_methode_paiement');
        Schema::table('users_app_methode_paiement', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur', 'id_methode']);
            $table->dropColumn('id_utilisateur');
            $table->dropColumn('id_methode');
        });
    }
};
