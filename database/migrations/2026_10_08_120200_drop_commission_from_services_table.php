<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le taux de commission par service n'a jamais été utilisé par le calcul
 * réel du montant facturé (qui dépend uniquement de la formule d'abonnement
 * de la coiffeuse, via AbonnementService). Le garder exigeait une saisie à
 * la création du service qui n'avait aucun effet, et son affichage côté
 * app annonçait à tort "commission de 15%" aux clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('commission');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->double('commission')->nullable()->default(0)->after('description');
        });
    }
};
