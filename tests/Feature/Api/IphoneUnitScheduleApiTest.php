<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IphoneUnitScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_unit_rental_schedule_with_timeline_and_summary(): void
    {
        $unit = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'asset_code' => 'AST-IP15P-SCHED1',
            'serial_number' => 'SN-IP15P-SCHED1',
            'status' => 'rented',
        ]);

        $today = Carbon::today('Asia/Jakarta');

        // 1. Past completed booking
        $past = Booking::create([
            'booking_code' => 'SKY-PAST-01',
            'iphone_id' => $unit->id,
            'customer_name' => 'Adit Nugroho',
            'customer_phone' => '081234567811',
            'start_booking_date' => $today->copy()->subDays(10)->toDateString(),
            'end_booking_date' => $today->copy()->subDays(7)->toDateString(),
            'duration' => 72,
            'price' => 500000,
            'status' => 'returned',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 2. Active currently rented booking
        $active = Booking::create([
            'booking_code' => 'SKY-ACTIVE-02',
            'iphone_id' => $unit->id,
            'customer_name' => 'Clara Shinta',
            'customer_phone' => '081234567822',
            'start_booking_date' => $today->copy()->subDays(1)->toDateString(),
            'end_booking_date' => $today->copy()->addDays(2)->toDateString(),
            'end_time' => '18:00',
            'duration' => 72,
            'price' => 600000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 3. Upcoming confirmed booking
        $upcoming = Booking::create([
            'booking_code' => 'SKY-UPCOMING-03',
            'iphone_id' => $unit->id,
            'customer_name' => 'Farhan Majid',
            'customer_phone' => '081234567833',
            'start_booking_date' => $today->copy()->addDays(5)->toDateString(),
            'end_booking_date' => $today->copy()->addDays(8)->toDateString(),
            'duration' => 72,
            'price' => 550000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Request schedule by asset code
        $response = $this->getJson("/api/v1/iphones/{$unit->asset_code}/schedule");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'total' => 3,
                    'aktif' => 1,
                    'mendatang' => 1,
                    'selesai' => 1,
                    'is_currently_rented' => true,
                ],
            ])
            ->assertJsonStructure([
                'status',
                'unit' => [
                    'id',
                    'name',
                    'asset_code',
                    'serial_number',
                    'status',
                ],
                'summary' => [
                    'total',
                    'aktif',
                    'mendatang',
                    'selesai',
                    'next_available_date',
                    'is_currently_rented',
                ],
                'current_rental',
                'upcoming_bookings',
                'rental_history',
                'data',
            ]);

        $this->assertEquals('SKY-ACTIVE-02', $response->json('current_rental.booking_code'));
        $this->assertCount(1, $response->json('upcoming_bookings'));
        $this->assertEquals('SKY-UPCOMING-03', $response->json('upcoming_bookings.0.booking_code'));
        $this->assertCount(1, $response->json('rental_history'));
        $this->assertEquals('SKY-PAST-01', $response->json('rental_history.0.booking_code'));
    }

    public function test_can_access_via_alias_and_numeric_id(): void
    {
        $unit = Iphones::factory()->create([
            'asset_code' => 'AST-IP14-ALIAS',
            'status' => 'ready',
        ]);

        // Via numeric ID
        $respId = $this->getJson("/api/v1/iphones/{$unit->id}/schedule");
        $respId->assertStatus(200)
            ->assertJsonFragment(['asset_code' => 'AST-IP14-ALIAS']);

        // Via alias /api/v1/units/{code}/schedule
        $respAlias = $this->getJson("/api/v1/units/{$unit->asset_code}/schedule");
        $respAlias->assertStatus(200)
            ->assertJsonFragment(['asset_code' => 'AST-IP14-ALIAS']);
    }

    public function test_can_filter_schedule_by_timeframe(): void
    {
        $unit = Iphones::factory()->create(['asset_code' => 'AST-TIMEFRAME']);
        $today = Carbon::today('Asia/Jakarta');

        Booking::create([
            'booking_code' => 'SKY-TF-ACTIVE',
            'iphone_id' => $unit->id,
            'customer_name' => 'Customer A',
            'customer_phone' => '08111111',
            'start_booking_date' => $today->toDateString(),
            'end_booking_date' => $today->copy()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 300000,
            'status' => 'rented',
            'created' => now(),
        ]);

        Booking::create([
            'booking_code' => 'SKY-TF-UPCOMING',
            'iphone_id' => $unit->id,
            'customer_name' => 'Customer B',
            'customer_phone' => '08222222',
            'start_booking_date' => $today->copy()->addDays(5)->toDateString(),
            'end_booking_date' => $today->copy()->addDays(7)->toDateString(),
            'duration' => 48,
            'price' => 300000,
            'status' => 'confirmed',
            'created' => now(),
        ]);

        Booking::create([
            'booking_code' => 'SKY-TF-PAST',
            'iphone_id' => $unit->id,
            'customer_name' => 'Customer C',
            'customer_phone' => '08333333',
            'start_booking_date' => $today->copy()->subDays(10)->toDateString(),
            'end_booking_date' => $today->copy()->subDays(8)->toDateString(),
            'duration' => 48,
            'price' => 300000,
            'status' => 'returned',
            'created' => now(),
        ]);

        // Filter: aktif
        $respActive = $this->getJson("/api/v1/iphones/{$unit->asset_code}/schedule?timeframe=aktif");
        $respActive->assertStatus(200);
        $this->assertCount(1, $respActive->json('data'));
        $this->assertEquals('SKY-TF-ACTIVE', $respActive->json('data.0.booking_code'));

        // Filter: mendatang
        $respUpcoming = $this->getJson("/api/v1/iphones/{$unit->asset_code}/schedule?timeframe=mendatang");
        $respUpcoming->assertStatus(200);
        $this->assertCount(1, $respUpcoming->json('data'));
        $this->assertEquals('SKY-TF-UPCOMING', $respUpcoming->json('data.0.booking_code'));

        // Filter: selesai
        $respPast = $this->getJson("/api/v1/iphones/{$unit->asset_code}/schedule?timeframe=selesai");
        $respPast->assertStatus(200);
        $this->assertCount(1, $respPast->json('data'));
        $this->assertEquals('SKY-TF-PAST', $respPast->json('data.0.booking_code'));
    }

    public function test_schedule_returns_404_when_unit_not_found(): void
    {
        $response = $this->getJson('/api/v1/iphones/NON-EXISTENT-UNIT/schedule');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
