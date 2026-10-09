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
        Schema::table('paiements', function (Blueprint $table) {
            // Identifiant ("id") renvoyé par Jèko à la création d'une demande
            // de paiement, distinct de provider_transaction_id (notre propre
            // référence). Nécessaire pour interroger GET
            // /partner_api/payment_requests/{id} (détection des paiements
            // échoués, qui n'envoient aucun webhook).
            $table->string('jeko_payment_request_id')->nullable()->after('provider_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('jeko_payment_request_id');
        });
    }
};
