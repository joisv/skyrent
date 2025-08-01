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
        Schema::create('duration_iphones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iphones_id')
                ->constrained('iphones')
                ->onDelete('cascade');
            $table->foreignId('duration_id')
                ->constrained('durations')
                ->onDelete('cascade');
            $table->decimal('price', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duration_iphones');
    }
};
