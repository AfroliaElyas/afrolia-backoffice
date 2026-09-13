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
        Schema::create('paiements', function (Blueprint $table) {
            $table->id('id_paiement');
            $table->unsignedBigInteger('id_reservation');
            $table->string('payment_intent_id', 200)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('XOF');
            $table->enum('payment_method', ['stripe', 'mobile_money']);
            $table->string('provider_transaction_id', 200)->nullable();
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreign('id_reservation')->references('id_reservation')->on('reservations')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropForeign(['id_reservation']);
            $table->dropColumn('id_reservation');
        });
    }
};
