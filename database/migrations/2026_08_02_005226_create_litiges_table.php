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
        Schema::create('litiges', function (Blueprint $table) {
            $table->id('id_litige');
            $table->unsignedBigInteger('id_reservation');
            $table->unsignedBigInteger('id_plaignant');
            $table->enum('type', ['qualite_service', 'paiement', 'comportement', 'annulation_abusive', 'autre'])->default('autre');
            $table->text('description');
            $table->enum('statut', ['ouvert', 'en_cours', 'resolu', 'rejete'])->default('ouvert');
            $table->text('resolution')->nullable();
            $table->timestamp('resolu_le')->nullable();
            $table->foreign('id_reservation')->references('id_reservation')->on('reservations')->onDelete('cascade');
            $table->foreign('id_plaignant')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('litiges');
    }
};
