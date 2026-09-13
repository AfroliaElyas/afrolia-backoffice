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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('id_reservation');
            $table->string('numero_reservation', 20)->unique();
            $table->unsignedBigInteger('id_client');
            $table->unsignedBigInteger('id_coiffeur');
            $table->unsignedBigInteger('id_service');
            $table->date('date_reservation');
            $table->time('heure_reservation');
            $table->enum('statut', ['en_attente', 'confirmee', 'en_cours', 'terminee', 'annulee', 'no_show'])->default('en_attente');
            $table->decimal('prix_service', 10, 2);
            $table->decimal('montant_commission', 10, 2);
            $table->decimal('montant_total', 10, 2);
            $table->enum('statut_paiement', ['en_attente', 'paye', 'rembourse', 'echoue'])->default('en_attente');
            $table->enum('methode_paiement', ['stripe', 'mobile_money', 'cash']);
            $table->text('notes')->nullable();
            $table->text('raison_annulation')->nullable();
            $table->enum('annule_par', ['client', 'coiffeur', 'admin'])->nullable();
            $table->timestamp('annule_le')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamp('termine_le')->nullable();

            $table->enum('statut_remboursement', ['aucun', 'en_attente', 'traite', 'rejete'])->default('aucun')->after('statut_paiement');
            $table->text('raison_rejet_remboursement')->nullable()->after('statut_remboursement');

            $table->foreign('id_client')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('id_coiffeur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->foreign('id_service')->references('id_service')->on('services')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['id_client', 'id_coiffeur', 'id_service']);
            $table->dropColumn('id_client');
            $table->dropColumn('id_coiffeur');
            $table->dropColumn('id_service');
        });
    }
};
