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
        Schema::create('services', function (Blueprint $table) {
            $table->id('id_service');
            $table->double('prix');
            $table->integer('minute');
            $table->string('description')->nullable();
            $table->double('commission');
            $table->unsignedBigInteger('id_utilisateur');
            $table->foreign('id_utilisateur')->references('id_user_app')->on('users_app')->onDelete('cascade');
            $table->unsignedBigInteger('id_speciale');
            $table->foreign('id_speciale')->references('id_specialite')->on('specialites')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['id_utilisateur','id_speciale']);
            $table->dropColumn('id_utilisateur');
            $table->dropColumn('id_speciale');
        });
    }
};
