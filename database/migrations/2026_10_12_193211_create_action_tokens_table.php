<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jetons à usage unique envoyés par e-mail (vérification d'adresse,
     * lien de réinitialisation de mot de passe). Seul le hash du jeton est
     * stocké : une fuite de la base ne donne jamais un jeton utilisable.
     */
    public function up(): void
    {
        Schema::create('action_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user_app')->constrained('users_app', 'id_user_app')->cascadeOnDelete();
            $table->enum('type', ['verification_email', 'reinitialisation_mot_de_passe']);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expire_le');
            $table->timestamp('utilise_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_tokens');
    }
};
