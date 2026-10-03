<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\ShopSetting;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopSettingsMigrationAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_settings_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('shop_settings'), 'Table shop_settings must exist.');
        $this->assertTrue(Schema::hasColumns('shop_settings', [
            'id',
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
            'created_at',
            'updated_at',
        ]));
    }

    public function test_shop_settings_seeder_populates_default_configuration(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $primary = ShopSetting::primary();
        $this->assertNotNull($primary);
        $this->assertEquals('SKYRental iPhone POS', $primary->shop_name);
        $this->assertEquals('Outlet Utama Malioboro', $primary->outlet_name);
        $this->assertStringContainsString('Malioboro', $primary->address);
        $this->assertEquals('0812-3456-7890', $primary->phone_number);
        $this->assertStringContainsString('SKYRental', $primary->footer_note);
        $this->assertEquals('SKYRENTAL_GUEST', $primary->wifi_name);
        $this->assertEquals('rentaliphoneoke', $primary->wifi_password);

        // Verify key-value rows
        $this->assertDatabaseHas('shop_settings', [
            'key' => 'shop_name',
            'value' => 'SKYRental iPhone POS',
        ]);
        $this->assertDatabaseHas('shop_settings', [
            'key' => 'outlet_name',
            'value' => 'Outlet Utama Malioboro',
        ]);
    }

    public function test_setting_model_get_and_set_methods(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $shopName = Setting::get('shop_name');
        $this->assertEquals('SKYRental iPhone POS', $shopName);

        Setting::set('shop_name', 'SKYRental Malioboro POS Official');
        $updated = Setting::get('shop_name');
        $this->assertEquals('SKYRental Malioboro POS Official', $updated);

        // Verify primary record also synced
        $primary = ShopSetting::primary();
        $this->assertEquals('SKYRental Malioboro POS Official', $primary->shop_name);
    }
}
