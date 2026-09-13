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
        Schema::create('client_favorites', function (Blueprint $table) {
            $table->id('id_client_favorite');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('stylist_id');
            $table->foreign('client_id')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('stylist_id')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->unique(['client_id', 'stylist_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_favorites');
        Schema::table('client_favorites', function (Blueprint $table) {
            $table->dropForeign(['client_id', 'stylist_id']);
            $table->dropColumn('client_id');
            $table->dropColumn('stylist_id');
        });
    }
};
