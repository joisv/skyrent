<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
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
     * Get or create the main primary shop configuration record.
     */
    public static function primary(): self
    {
        return static::firstOrCreate(
            ['is_active' => true],
            [
                'shop_name' => 'SKYRental iPhone POS',
                'outlet_name' => 'Outlet Utama Malioboro',
                'address' => 'Jl. Malioboro No. 45, Danurejan, D.I. Yogyakarta',
                'phone_number' => '0812-3456-7890',
                'footer_note' => 'Terima kasih telah mempercayakan sewa iPhone kepada SKYRental',
                'wifi_name' => 'SKYRENTAL_GUEST',
                'wifi_password' => 'rentaliphoneoke',
                'group' => 'shop',
            ]
        );
    }

    /**
     * Update primary shop configuration with provided array.
     */
    public static function updatePrimary(array $data): self
    {
        $setting = static::primary();
        $setting->update($data);
        return $setting;
    }
}
