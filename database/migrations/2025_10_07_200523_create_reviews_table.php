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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id('id_review');
            $table->unsignedBigInteger('id_reservation');
            $table->unsignedBigInteger('id_client');
            $table->unsignedBigInteger('id_stylist');
            $table->integer('rating')->check('rating >= 1 AND rating <= 5');
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->foreign('id_reservation')->references('id_reservation')->on('reservations')->onDelete('cascade');
            $table->foreign('id_client')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('id_stylist')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['id_client', 'id_reservation', 'id_stylist']);
            $table->dropColumn('id_client');
            $table->dropColumn('id_reservation');
            $table->dropColumn('id_stylist');
        });
    }
};
