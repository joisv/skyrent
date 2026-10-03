<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'shop_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
        'shop_name',
        'outlet_name',
        'address',
        'phone_number',
        'footer_note',
        'wifi_name',
        'wifi_password',
        'payload',
        'is_active',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get a setting by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();
        if ($record && $record->value !== null) {
            return $record->value;
        }

        // Fallback to primary shop setting columns
        $primary = ShopSetting::primary();
        if (isset($primary->{$key})) {
            return $primary->{$key};
        }

        return $default;
    }

    /**
     * Set a setting key-value pair.
     */
    public static function set(string $key, mixed $value, string $group = 'shop'): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'group' => $group,
            ]
        );

        // Also sync to primary shop setting if matching column exists
        $primary = ShopSetting::primary();
        if (in_array($key, ['shop_name', 'outlet_name', 'address', 'phone_number', 'footer_note', 'wifi_name', 'wifi_password'])) {
            $primary->update([$key => $value]);
        }

        return $setting;
    }
}
