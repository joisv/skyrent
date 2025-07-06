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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('iphone_id');

            // Data penyewa non-login
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->decimal('price', 12, 2)->default(0.00);
            

            // Waktu & durasi
            $table->date('start_booking_date')->default(now()->toDateString());
            $table->date('end_booking_date')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->integer('duration');

            $table->foreign('iphone_id')->references('id')->on('iphones')->onDelete('cascade');
            $table->enum('status', ['pending', 'confirmed', 'returned', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
