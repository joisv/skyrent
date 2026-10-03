<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingDeposit;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnActiveRentalsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_rentals_with_summary(): void
    {
        $today = Carbon::today()->toDateString();
        $iphone1 = Iphones::factory()->create(['name' => 'iPhone 15 Pro 128GB']);
        $iphone2 = Iphones::factory()->create(['name' => 'iPhone 14 Pro 256GB']);
        $iphone3 = Iphones::factory()->create(['name' => 'iPhone 13 128GB']);

        // 1. Rented booking (Active)
        $b1 = Booking::create([
            'booking_code' => 'SKY260909ACT01',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Kevin Sanjaya',
            'customer_phone' => '081234567801',
            'jaminan_type' => 'KTP Asli',
            'start_booking_date' => Carbon::today()->subDays(2)->toDateString(),
            'end_booking_date' => $today,
            'end_time' => '23:59',
            'duration' => 48,
            'price' => 500000,
            'deposit_amount' => 200000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 2. Rented booking (Overdue)
        $b2 = Booking::create([
            'booking_code' => 'SKY260909ACT02',
            'iphone_id' => $iphone2->id,
            'customer_name' => 'Marcus Gideon',
            'customer_phone' => '081234567802',
            'jaminan_type' => 'SIM A',
            'start_booking_date' => Carbon::today()->subDays(4)->toDateString(),
            'end_booking_date' => Carbon::today()->subDays(1)->toDateString(),
            'end_time' => '12:00',
            'duration' => 72,
            'price' => 750000,
            'deposit_amount' => 200000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 3. Confirmed booking (not rented yet - should be excluded)
        Booking::create([
            'booking_code' => 'SKY260909ACT03',
            'iphone_id' => $iphone3->id,
            'customer_name' => 'Belum Ambil',
            'customer_phone' => '081234567803',
            'start_booking_date' => $today,
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 450000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson('/api/v1/returns/active-rentals');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'booking_code',
                        'customer_name',
                        'customer_phone',
                        'jaminan_type',
                        'status',
                        'is_late',
                        'is_overdue',
                        'is_due_today',
                        'iphone' => [
                            'id',
                            'name',
                            'status',
                            'battery_health',
                        ],
                    ],
                ],
                'summary' => [
                    'total_active_rentals',
                    'total_overdue',
                    'total_due_today',
                    'total_active_deposit',
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('summary.total_active_rentals'));
        $this->assertEquals(1, $response->json('summary.total_overdue'));
        $this->assertEquals(1, $response->json('summary.total_due_today'));
    }

    public function test_can_access_via_alias_routes(): void
    {
        $iphone = Iphones::factory()->create();
        Booking::create([
            'booking_code' => 'SKY260909ALIAS01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Alias Rental',
            'customer_phone' => '081299990001',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $resp1 = $this->getJson('/api/v1/returns/active');
        $resp1->assertStatus(200)
            ->assertJson(['status' => 'success']);
        $this->assertNotEmpty($resp1->json('data'));

        $resp2 = $this->getJson('/api/v1/rentals/active');
        $resp2->assertStatus(200)
            ->assertJson(['status' => 'success']);
        $this->assertNotEmpty($resp2->json('data'));
    }

    public function test_can_search_active_rentals_by_query(): void
    {
        $iphone1 = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB Blue',
            'asset_code' => 'AST-IP15P-003',
            'serial_number' => 'Z3X456CV7B',
        ]);
        $iphone2 = Iphones::factory()->create([
            'name' => 'iPhone 14 Standard',
            'asset_code' => 'AST-IP14-001',
        ]);

        Booking::create([
            'booking_code' => 'SKY260909SRCH01',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Hendrawan Kusuma',
            'customer_phone' => '087711223399',
            'jaminan_type' => 'Motor Beat 2022',
            'duration' => 24,
            'price' => 450000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        Booking::create([
            'booking_code' => 'SKY260909SRCH02',
            'iphone_id' => $iphone2->id,
            'customer_name' => 'Dewi Anggraini',
            'customer_phone' => '087711223388',
            'jaminan_type' => 'KTP',
            'duration' => 24,
            'price' => 300000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Search by customer
        $resp = $this->getJson('/api/v1/returns/active-rentals?q=Hendrawan');
        $resp->assertStatus(200);
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('SKY260909SRCH01', $resp->json('data.0.booking_code'));

        // Search by asset code
        $respAsset = $this->getJson('/api/v1/returns/active-rentals?q=AST-IP15P-003');
        $respAsset->assertStatus(200);
        $this->assertCount(1, $respAsset->json('data'));

        // Search by serial number
        $respSerial = $this->getJson('/api/v1/returns/active-rentals?q=Z3X456CV7B');
        $respSerial->assertStatus(200);
        $this->assertCount(1, $respSerial->json('data'));

        // Search by jaminan
        $respJaminan = $this->getJson('/api/v1/returns/active-rentals?q=Beat');
        $respJaminan->assertStatus(200);
        $this->assertCount(1, $respJaminan->json('data'));
    }

    public function test_can_filter_overdue_and_due_today(): void
    {
        $today = Carbon::today()->toDateString();
        $iphone = Iphones::factory()->create();

        // Overdue booking
        Booking::create([
            'booking_code' => 'SKY260909OVD01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'User Overdue',
            'customer_phone' => '081299990002',
            'start_booking_date' => Carbon::today()->subDays(3)->toDateString(),
            'end_booking_date' => Carbon::today()->subDays(1)->toDateString(),
            'duration' => 48,
            'price' => 400000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Due today booking
        Booking::create([
            'booking_code' => 'SKY260909DUE01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'User Due Today',
            'customer_phone' => '081299990003',
            'start_booking_date' => Carbon::today()->subDays(1)->toDateString(),
            'end_booking_date' => $today,
            'duration' => 24,
            'price' => 300000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Test filter overdue
        $respOverdue = $this->getJson('/api/v1/returns/active-rentals?is_overdue=true');
        $respOverdue->assertStatus(200);
        $this->assertTrue(collect($respOverdue->json('data'))->contains('booking_code', 'SKY260909OVD01'));
        $this->assertFalse(collect($respOverdue->json('data'))->contains('booking_code', 'SKY260909DUE01'));

        // Test filter due today
        $respDue = $this->getJson('/api/v1/returns/active-rentals?is_due_today=true');
        $respDue->assertStatus(200);
        $this->assertTrue(collect($respDue->json('data'))->contains('booking_code', 'SKY260909DUE01'));
        $this->assertFalse(collect($respDue->json('data'))->contains('booking_code', 'SKY260909OVD01'));
    }

    public function test_can_get_standalone_return_summary(): void
    {
        $iphone = Iphones::factory()->create();
        Booking::create([
            'booking_code' => 'SKY260909SUM01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'User Summary',
            'customer_phone' => '081299990004',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'deposit_amount' => 200000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson('/api/v1/returns/summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_active_rentals' => 1,
                    'total_due_today' => 1,
                    'totalActiveRentals' => 1,
                ],
            ]);
    }
}
