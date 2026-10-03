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
        if (!Schema::hasTable('shop_settings')) {
            Schema::create('shop_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->nullable()->index();
                $table->text('value')->nullable();
                $table->string('group')->default('shop')->index();
                $table->string('shop_name')->default('SKYRental iPhone POS');
                $table->string('outlet_name')->default('Outlet Utama Malioboro');
                $table->text('address')->nullable();
                $table->string('phone_number')->nullable();
                $table->text('footer_note')->nullable();
                $table->string('wifi_name')->nullable();
                $table->string('wifi_password')->nullable();
                $table->json('payload')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
