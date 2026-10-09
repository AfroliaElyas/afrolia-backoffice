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
            // Opérateur et numéro utilisés pour l'encaissement Mobile Money,
            // conservés pour pouvoir créer le contact bénéficiaire nécessaire
            // à un remboursement automatique plus tard (voir
            // JekoGateway::refund()) — sans dépendre d'une saisie manuelle.
            $table->string('mobile_money_operateur')->nullable()->after('jeko_payment_request_id');
            $table->string('mobile_money_telephone')->nullable()->after('mobile_money_operateur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['mobile_money_operateur', 'mobile_money_telephone']);
        });
    }
};
