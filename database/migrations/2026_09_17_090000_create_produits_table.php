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
        Schema::create('produits', function (Blueprint $table) {
            $table->id('id_produit');
            $table->unsignedBigInteger('id_coiffeur');
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2);
            $table->unsignedInteger('quantite_stock')->default(0);
            $table->string('photo')->nullable();
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->foreign('id_coiffeur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
