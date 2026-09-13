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
        Schema::create('gallery', function (Blueprint $table) {
            $table->id('id_gallery');
            $table->string('image', 255);
            $table->string('description', 500)->nullable();
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
        Schema::dropIfExists('gallery');
        Schema::table('gallery', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur']);
            $table->dropColumn('id_utilisateur');
        });
    }
};
