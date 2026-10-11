<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un versement regroupe tous les gains "en_attente" d'une coiffeuse au
 * moment où un admin le prépare, pour un virement Mobile Money unique.
 * Deux étapes distinctes (préparer / confirmer) pour qu'aucun transfert
 * réel ne parte sans une action explicite et séparée de l'admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements', function (Blueprint $table) {
            $table->id('id_versement');
            $table->unsignedBigInteger('id_coiffeur');
            $table->decimal('montant', 10, 2);
            $table->decimal('frais_jeko', 10, 2)->nullable();
            $table->enum('statut', ['a_valider', 'en_cours', 'reussi', 'echoue', 'annule'])->default('a_valider');
            $table->string('reference', 60)->unique();
            $table->string('id_transaction_jeko', 100)->nullable();
            $table->text('erreur')->nullable();
            $table->unsignedBigInteger('declenche_par')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamps();

            $table->foreign('id_coiffeur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('declenche_par')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('gains', function (Blueprint $table) {
            $table->unsignedBigInteger('id_versement')->nullable()->after('statut');
            $table->foreign('id_versement')->references('id_versement')->on('versements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gains', function (Blueprint $table) {
            $table->dropForeign(['id_versement']);
            $table->dropColumn('id_versement');
        });

        Schema::dropIfExists('versements');
    }
};
