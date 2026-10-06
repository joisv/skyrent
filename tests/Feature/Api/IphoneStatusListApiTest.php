<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IphoneStatusListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_iphones_with_current_status_and_summary(): void
    {
        $readyUnit = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'status' => 'ready',
        ]);

        $rentedUnit = Iphones::factory()->create([
            'name' => 'iPhone 14 Pro Max',
            'status' => 'rented',
        ]);

        $maintUnit = Iphones::factory()->create([
            'name' => 'iPhone 13',
            'status' => 'maintenance',
        ]);

        // Create active rental booking for rentedUnit
        $booking = Booking::create([
            'booking_code' => 'SKY260909TEST01',
            'iphone_id' => $rentedUnit->id,
            'customer_name' => 'Dimas Pratama',
            'customer_phone' => '081234567888',
            'start_booking_date' => Carbon::today()->subDay()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_time' => '17:00',
            'duration' => 72,
            'price' => 450000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson('/api/v1/iphones');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'total' => 3,
                'summary' => [
                    'total' => 3,
                    'tersedia' => 1,
                    'disewa' => 1,
                    'maintenance' => 1,
                    'dibooking' => 0,
                    'ready' => 1,
                    'rented' => 1,
                ],
            ])
            ->assertJsonStructure([
                'status',
                'total',
                'summary' => [
                    'total',
                    'tersedia',
                    'disewa',
                    'maintenance',
                    'dibooking',
                ],
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'status_label',
                        'is_available',
                        'storage',
                        'color',
                        'battery_health',
                        'physical_condition',
                    ],
                ],
            ]);

        // Verify active booking included in rented unit
        $data = collect($response->json('data'));
        $rentedData = $data->firstWhere('id', $rentedUnit->id);
        $this->assertNotNull($rentedData['active_booking']);
        $this->assertEquals('SKY260909TEST01', $rentedData['active_booking']['booking_code']);
        $this->assertEquals('Dimas Pratama', $rentedData['active_booking']['customer_name']);
    }

    public function test_can_get_standalone_unit_status_summary(): void
    {
        Iphones::factory()->count(3)->create(['status' => 'ready']);
        Iphones::factory()->count(2)->create(['status' => 'rented']);
        Iphones::factory()->count(1)->create(['status' => 'maintenance']);

        $response = $this->getJson('/api/v1/iphones/summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 6,
                    'tersedia' => 3,
                    'disewa' => 2,
                    'maintenance' => 1,
                    'dibooking' => 0,
                ],
            ]);

        // Also test alias /api/v1/units/summary
        $aliasResponse = $this->getJson('/api/v1/units/summary');
        $aliasResponse->assertStatus(200)
            ->assertJsonFragment(['total' => 6]);
    }

    public function test_can_filter_units_by_status(): void
    {
        Iphones::factory()->create(['name' => 'Ready 1', 'status' => 'ready']);
        Iphones::factory()->create(['name' => 'Rented 1', 'status' => 'rented']);
        Iphones::factory()->create(['name' => 'Maint 1', 'status' => 'maintenance']);

        // Filter using Indonesian term 'tersedia'
        $respTersedia = $this->getJson('/api/v1/iphones?status=tersedia');
        $respTersedia->assertStatus(200)
            ->assertJson(['total' => 1])
            ->assertJsonFragment(['name' => 'Ready 1']);

        // Filter using English term 'rented'
        $respRented = $this->getJson('/api/v1/iphones?status=rented');
        $respRented->assertStatus(200)
            ->assertJson(['total' => 1])
            ->assertJsonFragment(['name' => 'Rented 1']);

        // Filter using Indonesian term 'perawatan'
        $respMaint = $this->getJson('/api/v1/iphones?status=perawatan');
        $respMaint->assertStatus(200)
            ->assertJson(['total' => 1])
            ->assertJsonFragment(['name' => 'Maint 1']);
    }

    public function test_can_search_units_by_query_string(): void
    {
        Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max',
            'asset_code' => 'AST-IP15PM-001',
            'serial_number' => 'SN-SPEC-7788',
            'description' => '512GB Blue Titanium',
        ]);

        Iphones::factory()->create([
            'name' => 'iPhone 11',
            'asset_code' => 'AST-IP11-002',
            'serial_number' => 'SN-REG-1122',
            'description' => '64GB Black',
        ]);

        // Search by serial
        $respSerial = $this->getJson('/api/v1/iphones?q=SPEC-7788');
        $respSerial->assertStatus(200)
            ->assertJson(['total' => 1])
            ->assertJsonFragment(['name' => 'iPhone 15 Pro Max']);

        // Search by storage
        $respStorage = $this->getJson('/api/v1/iphones?q=512GB');
        $respStorage->assertStatus(200)
            ->assertJson(['total' => 1]);

        // Search by color
        $respColor = $this->getJson('/api/v1/iphones?q=Blue');
        $respColor->assertStatus(200)
            ->assertJson(['total' => 1]);
    }

    public function test_pagination_and_sorting(): void
    {
        Iphones::factory()->create(['name' => 'iPhone A']);
        Iphones::factory()->create(['name' => 'iPhone B']);
        Iphones::factory()->create(['name' => 'iPhone C']);

        $response = $this->getJson('/api/v1/iphones?per_page=2&sort_by=name&sort_dir=asc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'summary',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(2, count($response->json('data')));
        $this->assertEquals(3, $response->json('meta.total'));
        $this->assertEquals('iPhone A', $response->json('data.0.name'));
    }
}
