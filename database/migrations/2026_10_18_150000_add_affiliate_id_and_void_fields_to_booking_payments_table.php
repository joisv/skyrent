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
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreignId('affiliate_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('affiliates')
                ->nullOnDelete();

            $table->boolean('is_void')
                ->default(false)
                ->after('note')
                ->index();

            $table->string('void_reason')
                ->nullable()
                ->after('is_void');

            $table->foreignUuid('voided_by')
                ->nullable()
                ->after('void_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')
                ->nullable()
                ->after('voided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn([
                'affiliate_id',
                'is_void',
                'void_reason',
                'voided_by',
                'voided_at',
            ]);
        });
    }
};
