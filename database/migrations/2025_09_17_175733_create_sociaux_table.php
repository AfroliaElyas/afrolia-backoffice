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
        Schema::create('sociaux', function (Blueprint $table) {
            $table->id('id_sociaux');
            $table->string('instagram', 255);
            $table->string('facebook', 255);
            $table->string('whatsapp', 100);
            $table->string('tiktok', 255);
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
        Schema::dropIfExists('sociaux');
        Schema::table('sociaux', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur']);
            $table->dropColumn('id_utilisateur');
        });
    }
};
