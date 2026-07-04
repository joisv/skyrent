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
        Schema::create('iphone_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iphone_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('from_affiliate_id')
                ->constrained('affiliates');

            $table->foreignId('to_affiliate_id')
                ->constrained('affiliates');

            $table->foreignUuid('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'pending',
                'in_transit',
                'received',
                'cancelled',
            ])->default('pending');

            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iphone_transfers');
    }
};
