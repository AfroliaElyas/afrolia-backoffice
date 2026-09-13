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
        Schema::create('users_app', function (Blueprint $table) {
            $table->id('id_user_app');
            $table->string('photo')->nullable();
            $table->string('name', 100);
            $table->string('last_name', 255);
            $table->string('phone', 100)->unique();
            $table->string('email', 100)->unique()->nullable();
            $table->longText('presentation', 500)->nullable();
            $table->string('commune', 100)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->integer('experience')->nullable();
            $table->integer('otp')->nullable();
            $table->string('password', 255);
            $table->string('role', 50)->comment('hair, user')->default('user');
            $table->string('statut', 50)->comment('Active, Inactive')->default('Active');
            $table->text('raison_suspension')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_app');
    }
};
