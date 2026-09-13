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
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id('id_disponibilite');
            $table->unsignedBigInteger('id_day');
            $table->foreign('id_day')->references('id_jour')->on('jours')->onDelete('cascade');
            $table->unsignedBigInteger('id_time');
            $table->foreign('id_time')->references('id_heure')->on('heures')->onDelete('cascade');
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
        Schema::dropIfExists('disponibilites');
        Schema::table('disponibilites', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur','id_day','id_time']);
            $table->dropColumn('id_utilisateur');
            $table->dropColumn('id_day');
            $table->dropColumn('id_time');
        });
    }
};
