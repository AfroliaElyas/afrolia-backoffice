<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('otp_created_at');
        });

        // Les comptes qui existaient déjà avant cette fonctionnalité ont été
        // créés sans vérification par SMS : les considérer vérifiés plutôt
        // que de bloquer leur connexion après coup.
        DB::table('users_app')->update(['phone_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
