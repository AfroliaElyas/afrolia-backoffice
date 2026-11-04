<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->boolean('renouvellement_automatique')->default(true)->after('prochain_prelevement_le');
        });
    }

    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn('renouvellement_automatique');
        });
    }
};
