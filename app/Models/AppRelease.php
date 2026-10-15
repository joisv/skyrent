<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AppRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'version_name',
        'build_number',
        'min_supported_build',
        'release_notes',
        'apk_path',
        'apk_size',
        'apk_checksum_sha256',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'min_supported_build' => 'integer',
        'apk_size' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleted(function (AppRelease $release) {
            if ($release->apk_path && Storage::disk('local')->exists($release->apk_path)) {
                Storage::disk('local')->delete($release->apk_path);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('build_number', 'desc');
    }

    public static function latestActive(): ?self
    {
        return static::active()->first();
    }
}
