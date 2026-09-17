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
        Schema::create('commande_lignes', function (Blueprint $table) {
            $table->id('id_ligne');
            $table->unsignedBigInteger('id_commande');
            $table->unsignedBigInteger('id_produit');
            // Nom et prix "snapshotés" au moment de la commande : l'historique
            // ne doit pas changer si le produit est ensuite modifié/supprimé.
            $table->string('nom_produit', 150);
            $table->decimal('prix_unitaire', 10, 2);
            $table->unsignedInteger('quantite');
            $table->decimal('sous_total', 10, 2);
            $table->foreign('id_commande')->references('id_commande')->on('commandes')->onDelete('cascade');
            $table->foreign('id_produit')->references('id_produit')->on('produits')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commande_lignes');
    }
};
