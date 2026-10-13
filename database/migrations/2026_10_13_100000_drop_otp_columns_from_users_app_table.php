<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La réinitialisation de mot de passe par SMS (Twilio) est abandonnée
     * au profit de l'e-mail (Brevo) et des codes de secours : ces colonnes
     * n'ont plus aucun code qui les utilise.
     */
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn(['otp', 'otp_created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->integer('otp')->nullable();
            $table->timestamp('otp_created_at')->nullable()->after('otp');
        });
    }
};
