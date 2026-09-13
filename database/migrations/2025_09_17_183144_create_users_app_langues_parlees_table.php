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
        Schema::create('users_app_langues_parlees', function (Blueprint $table) {
            $table->id('id_user_app_langue_parle');
            $table->unsignedBigInteger('id_utilisateur');
            $table->foreign('id_utilisateur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->unsignedBigInteger('id_language');
            $table->foreign('id_language')->references('id_langue')->on('langues')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_app_langues_parlees');
        Schema::table('users_app_langues_parlees', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur','id_language']);
            $table->dropColumn('id_utilisateur');
            $table->dropColumn('id_language');
        });
    }
};
