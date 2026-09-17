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
        Schema::create('commandes', function (Blueprint $table) {
            $table->id('id_commande');
            $table->string('numero_commande', 20)->unique();
            $table->unsignedBigInteger('id_client');
            $table->unsignedBigInteger('id_coiffeur');
            $table->decimal('montant_produits', 10, 2);
            $table->decimal('montant_commission', 10, 2);
            $table->decimal('montant_total', 10, 2);
            $table->enum('methode_paiement', ['stripe', 'mobile_money']);
            $table->enum('statut_paiement', ['en_attente', 'paye', 'echoue'])->default('en_attente');
            $table->enum('statut_commande', ['en_attente', 'payee', 'expediee', 'livree', 'annulee'])->default('en_attente');
            $table->text('raison_annulation')->nullable();
            $table->timestamp('expediee_le')->nullable();
            $table->timestamp('livree_le')->nullable();
            $table->timestamp('annulee_le')->nullable();
            $table->foreign('id_client')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('id_coiffeur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
