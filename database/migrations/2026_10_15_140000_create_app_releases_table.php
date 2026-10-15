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
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version_name'); // e.g. '1.0.1'
            $table->unsignedInteger('build_number')->unique(); // e.g. 2
            $table->unsignedInteger('min_supported_build')->nullable(); // For mandatory updates
            $table->text('release_notes')->nullable();
            $table->string('apk_path'); // File path in storage/app/private/apks/
            $table->unsignedBigInteger('apk_size'); // Size in bytes
            $table->string('apk_checksum_sha256', 64); // SHA-256 integrity hash
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'build_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
