<?php

namespace Tests\Feature\Api;

use App\Models\ShopSetting;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_default_shop_settings(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $response = $this->getJson('/api/v1/settings/shop');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shop_name' => 'SKYRental iPhone POS',
                    'outlet_name' => 'Outlet Utama Malioboro',
                    'wifi_name' => 'SKYRENTAL_GUEST',
                    'wifi_password' => 'rentaliphoneoke',
                ],
            ]);

        $this->assertStringContainsString('Malioboro', $response->json('data.address'));
        $this->assertNotEmpty($response->json('data.phone_number'));
    }

    public function test_can_get_shop_settings_via_alias_route(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $response = $this->getJson('/api/v1/shop-settings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shop_name' => 'SKYRental iPhone POS',
                    'outlet_name' => 'Outlet Utama Malioboro',
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_update_shop_settings(): void
    {
        $response = $this->putJson('/api/v1/settings/shop', [
            'shop_name' => 'SKYRental Updated Name',
            'outlet_name' => 'Outlet Baru',
            'address' => 'Jl. Baru No. 12',
            'phone_number' => '0811-2233-4455',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_shop_settings_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/settings/shop', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shop_name', 'outlet_name', 'address', 'phone_number']);
    }

    public function test_authenticated_user_can_update_shop_settings(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'shop_name' => 'SKYRental Premium Jogja',
            'outlet_name' => 'Cabang Tugu Malioboro',
            'address' => 'Jl. Margo Utomo No. 88, Gowongan, Jetis, Yogyakarta',
            'phone_number' => '0821-9988-7766',
            'footer_note' => 'Sewa iPhone Resmi Terpercaya Jogja - Garansi Unit Prima',
            'wifi_name' => 'SKYRENTAL_VIP',
            'wifi_password' => 'jogjaistimewa2026',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/settings/shop', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pengaturan toko berhasil diperbarui.',
                'data' => $payload,
            ]);

        // Verify that database reflects the update
        $primary = ShopSetting::primary();
        $this->assertEquals('SKYRental Premium Jogja', $primary->shop_name);
        $this->assertEquals('Cabang Tugu Malioboro', $primary->outlet_name);
        $this->assertEquals('0821-9988-7766', $primary->phone_number);
        $this->assertEquals('SKYRENTAL_VIP', $primary->wifi_name);

        // Verify subsequent GET returns the new data
        $getResponse = $this->getJson('/api/v1/settings/shop');
        $getResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'shop_name' => 'SKYRental Premium Jogja',
                    'outlet_name' => 'Cabang Tugu Malioboro',
                ],
            ]);
    }

    public function test_can_update_shop_settings_via_post_alias(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'shop_name' => 'SKYRental Express',
            'outlet_name' => 'Outlet Gejayan',
            'address' => 'Jl. Affandi Gejayan No. 10',
            'phone_number' => '0813-1122-3344',
            'footer_note' => 'Terima kasih',
            'wifi_name' => 'SKYRENT_GEJAYAN',
            'wifi_password' => 'gejayan123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/shop-settings', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shop_name' => 'SKYRental Express',
                    'outlet_name' => 'Outlet Gejayan',
                ],
            ]);

        $this->assertEquals('SKYRental Express', ShopSetting::primary()->shop_name);
    }
}