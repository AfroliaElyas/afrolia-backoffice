<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes de secours générés pendant que la personne est connectée, pour
     * récupérer l'accès à son compte plus tard sans SMS ni e-mail. Possession
     * d'un code non utilisé, pour le bon numéro de téléphone, constitue la
     * preuve d'identité (il n'a jamais été montré qu'à la propriétaire
     * authentifiée du compte). used_le/used_ip/used_user_agent conservent
     * une trace de chaque utilisation.
     */
    public function up(): void
    {
        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user_app')->constrained('users_app', 'id_user_app')->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->timestamp('utilise_le')->nullable();
            $table->string('utilise_ip', 45)->nullable();
            $table->string('utilise_user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_codes');
    }
};
