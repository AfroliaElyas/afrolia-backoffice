<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('adresse');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('derniere_maj_position')->nullable()->after('longitude');
            $table->boolean('deplacement_domicile')->default(false)->after('derniere_maj_position');
        });
    }

    public function down(): void
    {
        Schema::table('users_app', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'derniere_maj_position', 'deplacement_domicile']);
        });
    }
};
