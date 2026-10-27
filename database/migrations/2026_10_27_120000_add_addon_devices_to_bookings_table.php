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
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'addon_devices')) {
                $table->json('addon_devices')->nullable()->after('iphone_id');
            }
            if (!Schema::hasColumn('bookings', 'addon_subtotal')) {
                $table->decimal('addon_subtotal', 12, 2)->default(0.00)->after('price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'addon_devices')) {
                $table->dropColumn('addon_devices');
            }
            if (Schema::hasColumn('bookings', 'addon_subtotal')) {
                $table->dropColumn('addon_subtotal');
            }
        });
    }
};
