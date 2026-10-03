<?php

namespace Database\Seeders;

use App\Models\ShopSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed primary row
        ShopSetting::updateOrCreate(
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
                'payload' => [
                    'operating_hours' => '08:00 - 22:00',
                    'receipt_width_mm' => 58,
                    'auto_print' => true,
                    'city' => 'Yogyakarta',
                    'instagram' => '@skyrental.jogja',
                ],
            ]
        );

        // 2. Seed key-value pairs for flexible queries
        $keyValuePairs = [
            'shop_name' => 'SKYRental iPhone POS',
            'outlet_name' => 'Outlet Utama Malioboro',
            'address' => 'Jl. Malioboro No. 45, Danurejan, D.I. Yogyakarta',
            'phone_number' => '0812-3456-7890',
            'footer_note' => 'Terima kasih telah mempercayakan sewa iPhone kepada SKYRental',
            'wifi_name' => 'SKYRENTAL_GUEST',
            'wifi_password' => 'rentaliphoneoke',
            'operating_hours' => '08:00 - 22:00',
            'currency' => 'IDR',
            'receipt_paper_width' => '58mm',
        ];

        foreach ($keyValuePairs as $key => $val) {
            ShopSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $val,
                    'group' => 'shop',
                ]
            );
        }

        // 3. Also sync to settings table (spatie) if exists
        if (DB::getSchemaBuilder()->hasTable('settings')) {
            $spatieDefaults = [
                ['group' => 'shop', 'name' => 'shop_name', 'payload' => json_encode('SKYRental iPhone POS')],
                ['group' => 'shop', 'name' => 'outlet_name', 'payload' => json_encode('Outlet Utama Malioboro')],
                ['group' => 'shop', 'name' => 'address', 'payload' => json_encode('Jl. Malioboro No. 45, Danurejan, D.I. Yogyakarta')],
                ['group' => 'shop', 'name' => 'phone_number', 'payload' => json_encode('0812-3456-7890')],
                ['group' => 'shop', 'name' => 'footer_note', 'payload' => json_encode('Terima kasih telah mempercayakan sewa iPhone kepada SKYRental')],
                ['group' => 'shop', 'name' => 'wifi_name', 'payload' => json_encode('SKYRENTAL_GUEST')],
                ['group' => 'shop', 'name' => 'wifi_password', 'payload' => json_encode('rentaliphoneoke')],
            ];

            foreach ($spatieDefaults as $row) {
                DB::table('settings')->updateOrInsert(
                    ['group' => $row['group'], 'name' => $row['name']],
                    ['payload' => $row['payload'], 'locked' => false, 'updated_at' => now()]
                );
            }
        }
    }
}
