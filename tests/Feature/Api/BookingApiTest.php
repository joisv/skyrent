<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_bookings(): void
    {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_can_get_today_bookings_summary(): void
    {
        $response = $this->getJson('/api/v1/bookings/today');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'date',
                'summary' => [
                    'total_pickups_today',
                    'total_returns_today',
                    'total_created_today',
                ],
                'data' => [
                    'pickups',
                    'returns',
                    'created_today',
                ],
            ]);
    }

    public function test_can_search_and_filter_bookings(): void
    {
        $response = $this->getJson('/api/v1/bookings?search=SKY');
        $response->assertStatus(200);

        $responseStatus = $this->getJson('/api/v1/bookings?status=confirmed');
        $responseStatus->assertStatus(200);

        $responseToday = $this->getJson('/api/v1/bookings?today_only=1');
        $responseToday->assertStatus(200);
    }

    public function test_dedicated_search_endpoint(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max',
            'serial_number' => 'SN-IP15PM-SEARCH',
            'asset_code' => 'AST-SEARCH-001',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKYSEARCH1234',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Ahmad Searchable',
            'customer_phone' => '081299887766',
            'customer_email' => 'ahmad.search@example.com',
            'start_booking_date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => Carbon::today()->addDays(3)->toDateString(),
            'end_time' => '10:00',
            'duration' => 72,
            'price' => 600000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Search empty
        $responseEmpty = $this->getJson('/api/v1/bookings/search');
        $responseEmpty->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'total' => 0,
                'data' => [],
            ]);

        // Search by name
        $responseName = $this->getJson('/api/v1/bookings/search?q=Ahmad+Searchable');
        $responseName->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.booking_code', 'SKYSEARCH1234');

        // Search by booking code
        $responseCode = $this->getJson('/api/v1/bookings/search?q=SKYSEARCH1234');
        $responseCode->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.customer_name', 'Ahmad Searchable');

        // Search by phone
        $responsePhone = $this->getJson('/api/v1/bookings/search?q=081299887766');
        $responsePhone->assertStatus(200)
            ->assertJsonPath('total', 1);

        // Search by iPhone serial number
        $responseSerial = $this->getJson('/api/v1/bookings/search?q=SN-IP15PM-SEARCH');
        $responseSerial->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_can_get_booking_detail_by_code_and_id(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'serial_number' => 'F2LN798XK0G',
            'asset_code' => 'AST-IP15P-001',
            'status' => 'ready',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909TEST',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Budi Tester',
            'customer_phone' => '08123456789',
            'customer_email' => 'budi@tester.com',
            'pickup_type' => 'Outlet',
            'jaminan_type' => 'KTP',
            'start_booking_date' => Carbon::today()->toDateString(),
            'start_time' => '14:00',
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_time' => '14:00',
            'duration' => 48,
            'price' => 450000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Test by booking_code
        $responseCode = $this->getJson("/api/v1/bookings/{$booking->booking_code}");
        $responseCode->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909TEST',
                    'customer_name' => 'Budi Tester',
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                ],
            ]);

        // Test by ID
        $responseId = $this->getJson("/api/v1/bookings/{$booking->id}");
        $responseId->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $booking->id,
                    'booking_code' => 'SKY260909TEST',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_booking(): void
    {
        $response = $this->getJson('/api/v1/bookings/NON_EXISTENT_CODE_123');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}