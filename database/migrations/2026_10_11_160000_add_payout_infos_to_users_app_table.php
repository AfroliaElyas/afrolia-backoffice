<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro Mobile Money vers lequel verser les gains d'une coiffeuse.
 * Distinct du numéro de connexion (phone) : une coiffeuse peut vouloir
 * être payée sur un autre numéro que celui utilisé pour se connecter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->string('payout_telephone', 100)->nullable()->after('phone');
            $table->string('payout_operateur', 20)->nullable()->after('payout_telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn(['payout_telephone', 'payout_operateur']);
        });
    }
};
