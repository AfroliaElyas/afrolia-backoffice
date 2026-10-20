<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permet à une coiffeuse de payer son passage à la formule Premium
 * directement par carte (Stripe) ou Mobile Money, sans attendre d'avoir
 * déjà des gains disponibles sur la plateforme (jusqu'ici, Premium n'était
 * accessible qu'en prélèvement sur le solde de gains déjà perçus, via
 * AbonnementService::preleverPour — toujours utilisé pour le renouvellement
 * mensuel automatique, inchangé).
 *
 * - paiements.id_abonnement_paiement : troisième type de "payable" à côté
 *   de id_reservation/id_commande, pour réutiliser telles quelles la
 *   création de PaymentIntent Stripe / l'initiation Mobile Money et les
 *   webhooks déjà en place.
 * - abonnement_paiements.statut gagne "en_attente" : jusqu'ici le
 *   prélèvement sur solde était toujours immédiat (reussi/echec sans état
 *   intermédiaire), un paiement carte/Mobile Money a besoin d'un état
 *   "en attente de confirmation" le temps du webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->unsignedBigInteger('id_abonnement_paiement')->nullable()->after('id_commande');
            $table->foreign('id_abonnement_paiement')->references('id_abonnement_paiement')->on('abonnement_paiements')->onDelete('cascade');
        });

        // Contrairement à gains.statut (simple varchar), abonnement_paiements
        // avait été créée avec un vrai enum() : sous SQLite (tests), Laravel
        // le traduit en contrainte CHECK, qu'il faut donc aussi reconstruire
        // ici, pas seulement sous MySQL (production).
        Schema::table('abonnement_paiements', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'reussi', 'echec'])->default('en_attente')->change();
        });
    }

    public function down(): void
    {
        DB::table('abonnement_paiements')->where('statut', 'en_attente')->delete();

        Schema::table('abonnement_paiements', function (Blueprint $table) {
            $table->enum('statut', ['reussi', 'echec'])->change();
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropForeign(['id_abonnement_paiement']);
            $table->dropColumn('id_abonnement_paiement');
        });
    }
};
