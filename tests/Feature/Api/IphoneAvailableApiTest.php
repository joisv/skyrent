<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IphoneAvailableApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_only_available_iphones(): void
    {
        // Ready unit
        $readyUnit = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-READY',
            'asset_code' => 'AST-IP15P-001',
            'status' => 'ready',
        ]);

        // Tersedia unit
        $tersediaUnit = Iphones::factory()->create([
            'name' => 'iPhone 14 128GB',
            'serial_number' => 'SN-IP14-TERSEDIA',
            'asset_code' => 'AST-IP14-002',
            'status' => 'ready',
        ]);

        // Rented unit
        $rentedUnit = Iphones::factory()->create([
            'name' => 'iPhone 13 128GB',
            'serial_number' => 'SN-IP13-RENTED',
            'asset_code' => 'AST-IP13-003',
            'status' => 'rented',
        ]);

        // Maintenance unit
        $maintenanceUnit = Iphones::factory()->create([
            'name' => 'iPhone 12 128GB',
            'serial_number' => 'SN-IP12-MAINT',
            'asset_code' => 'AST-IP12-004',
            'status' => 'maintenance',
        ]);

        // Test GET /api/v1/iphones/available
        $response = $this->getJson('/api/v1/iphones/available');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'total_available' => 2,
            ])
            ->assertJsonFragment(['asset_code' => 'AST-IP15P-001'])
            ->assertJsonFragment(['asset_code' => 'AST-IP14-002'])
            ->assertJsonMissing(['asset_code' => 'AST-IP13-003'])
            ->assertJsonMissing(['asset_code' => 'AST-IP12-004']);

        // Test alias GET /api/v1/pickup/available-units
        $aliasResponse = $this->getJson('/api/v1/pickup/available-units');
        $aliasResponse->assertStatus(200)
            ->assertJson(['total_available' => 2]);
    }

    public function test_can_filter_available_units_by_model(): void
    {
        Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-15P',
            'asset_code' => 'AST-15P',
            'status' => 'ready',
        ]);

        Iphones::factory()->create([
            'name' => 'iPhone 13 128GB',
            'serial_number' => 'SN-13',
            'asset_code' => 'AST-13',
            'status' => 'ready',
        ]);

        $response = $this->getJson('/api/v1/iphones/available?model=iPhone 15');

        $response->assertStatus(200)
            ->assertJson(['total_available' => 1])
            ->assertJsonFragment(['asset_code' => 'AST-15P'])
            ->assertJsonMissing(['asset_code' => 'AST-13']);
    }

    public function test_can_search_available_units_by_query(): void
    {
        Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max',
            'serial_number' => 'SN-IP15PM-SEARCHME',
            'asset_code' => 'AST-IP15PM-888',
            'status' => 'ready',
        ]);

        Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'serial_number' => 'SN-OTHER',
            'asset_code' => 'AST-OTHER-999',
            'status' => 'ready',
        ]);

        // Search by serial number
        $response = $this->getJson('/api/v1/iphones/available?q=SEARCHME');
        $response->assertStatus(200)
            ->assertJson(['total_available' => 1])
            ->assertJsonFragment(['asset_code' => 'AST-IP15PM-888'])
            ->assertJsonMissing(['asset_code' => 'AST-OTHER-999']);

        // Search by asset code
        $response2 = $this->getJson('/api/v1/iphones/available?q=AST-IP15PM-888');
        $response2->assertStatus(200)
            ->assertJson(['total_available' => 1])
            ->assertJsonFragment(['serial_number' => 'SN-IP15PM-SEARCHME']);
    }

    public function test_auto_filters_by_booking_model_when_booking_code_given(): void
    {
        $iphoneTarget = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 256GB',
            'serial_number' => 'SN-TARGET-1',
            'asset_code' => 'AST-TARGET-001',
            'status' => 'ready',
        ]);

        $anotherIphoneSameModel = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 256GB',
            'serial_number' => 'SN-TARGET-2',
            'asset_code' => 'AST-TARGET-002',
            'status' => 'ready',
        ]);

        $iphoneDifferentModel = Iphones::factory()->create([
            'name' => 'iPhone 12 Mini',
            'serial_number' => 'SN-MINI-1',
            'asset_code' => 'AST-MINI-001',
            'status' => 'ready',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909MATCH',
            'iphone_id' => $iphoneTarget->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'duration' => 48,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'price' => 400000,
            'created' => now(),
        ]);

        // When passing booking_code, only matching iPhone model units should be returned
        $response = $this->getJson('/api/v1/iphones/available?booking_code=' . $booking->booking_code);

        $response->assertStatus(200)
            ->assertJson(['total_available' => 2])
            ->assertJsonFragment(['asset_code' => 'AST-TARGET-001'])
            ->assertJsonFragment(['asset_code' => 'AST-TARGET-002'])
            ->assertJsonMissing(['asset_code' => 'AST-MINI-001']);
    }

    public function test_can_get_single_iphone_by_asset_code_or_id(): void
    {
        $unit = Iphones::factory()->create([
            'name' => 'iPhone 14 Pro',
            'serial_number' => 'SN-14PRO-TEST',
            'asset_code' => 'AST-14P-999',
            'status' => 'ready',
        ]);

        // By ID
        $responseId = $this->getJson('/api/v1/iphones/' . $unit->id);
        $responseId->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $unit->id,
                    'name' => 'iPhone 14 Pro',
                    'asset_code' => 'AST-14P-999',
                    'serial_number' => 'SN-14PRO-TEST',
                    'is_available' => true,
                ],
            ]);

        // By Asset Code
        $responseAsset = $this->getJson('/api/v1/iphones/AST-14P-999');
        $responseAsset->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'asset_code' => 'AST-14P-999',
                ],
            ]);

        // Not found
        $responseNotFound = $this->getJson('/api/v1/iphones/NON-EXISTENT');
        $responseNotFound->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
