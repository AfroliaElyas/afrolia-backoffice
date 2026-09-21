<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->string('formule_abonnement', 20)->default('gratuit')->after('statut');
            $table->timestamp('prochain_prelevement_le')->nullable()->after('formule_abonnement');
        });
    }

    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn(['formule_abonnement', 'prochain_prelevement_le']);
        });
    }
};
