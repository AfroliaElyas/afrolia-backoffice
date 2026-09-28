<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * L'OTP de réinitialisation de mot de passe n'avait aucune date
     * d'expiration : un code généré une fois restait valable indéfiniment
     * tant qu'aucun nouveau code n'était demandé. Cette colonne permet de
     * borner sa validité dans le temps (voir
     * ApiUtilisateursController::resetPasswordWithOtp).
     */
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->timestamp('otp_created_at')->nullable()->after('otp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn('otp_created_at');
        });
    }
};
