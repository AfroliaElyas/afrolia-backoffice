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
        Schema::create('gains', function (Blueprint $table) {
            $table->id('id_gain');
            $table->unsignedBigInteger('id_coiffeur');
            $table->unsignedBigInteger('id_reservation');
            $table->decimal('montant_brut', 10, 2);
            $table->decimal('montant_commission', 10, 2);
            $table->decimal('montant_net', 10, 2);
            $table->enum('statut', ['en_attente', 'disponible', 'paye'])->default('en_attente');
            $table->timestamp('date_paiement')->nullable();
            $table->foreign('id_coiffeur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('id_reservation')->references('id_reservation')->on('reservations')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gains');
        Schema::table('gains', function (Blueprint $table) {
            $table->dropForeign(['id_coiffeur', 'id_reservation']);
            $table->dropColumn('id_coiffeur');
            $table->dropColumn('id_reservation');
        });
    }
};
