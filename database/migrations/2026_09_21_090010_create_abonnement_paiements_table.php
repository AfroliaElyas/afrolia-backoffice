<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnement_paiements', function (Blueprint $table) {
            $table->id('id_abonnement_paiement');
            $table->foreignId('id_coiffeur')->constrained('users_app', 'id_user_app')->cascadeOnDelete();
            $table->string('formule', 20);
            $table->decimal('montant', 10, 2);
            $table->enum('statut', ['reussi', 'echec']);
            $table->timestamp('date_prelevement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnement_paiements');
    }
};
