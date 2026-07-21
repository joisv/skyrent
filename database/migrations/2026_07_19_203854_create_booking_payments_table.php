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
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // mengacu ke tabel payments (Cash, Transfer, QRIS)
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->decimal('pay', 15, 2);     
            $table->decimal('change', 15, 2);
            // dp, pelunasan, extend, penalty, refund
            $table->enum('type', [
                'dp',
                'payment',
                'extend',
                'penalty',
                'refund'
            ])->default('payment');

            $table->timestamp('paid_at');

            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
