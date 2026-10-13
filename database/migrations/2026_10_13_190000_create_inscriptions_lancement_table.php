<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adresses e-mail laissées sur le site public pour être prévenues du
     * lancement d'Afrolia (page d'accueil, formulaire "être prévenue").
     */
    public function up(): void
    {
        Schema::create('inscriptions_lancement', function (Blueprint $table) {
            $table->id('id_inscription_lancement');
            $table->string('email', 150)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions_lancement');
    }
};
