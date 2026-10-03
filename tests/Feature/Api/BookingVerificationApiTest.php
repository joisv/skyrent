<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_booking_verification_detail_for_confirmed_booking(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'serial_number' => 'SN-IP15P-VERIFY',
            'asset_code' => 'AST-IP15P-001',
            'status' => 'ready',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909CONF',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Dimas Surya',
            'customer_phone' => '081234567891',
            'customer_email' => 'dimas@example.com',
            'address' => 'Jl. Kaliurang KM 5 No. 12, Yogyakarta',
            'pickup_type' => 'Outlet',
            'jaminan_type' => 'KTP Asli',
            'start_booking_date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_time' => '10:00',
            'duration' => 48,
            'price' => 450000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 1. Test via /api/v1/bookings/{idOrCode}/verify
        $response = $this->getJson("/api/v1/bookings/{$booking->booking_code}/verify");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'can_pickup' => true,
                'data' => [
                    'booking_code' => 'SKY260909CONF',
                    'customer_name' => 'Dimas Surya',
                    'can_pickup' => true,
                ],
                'verification' => [
                    'can_pickup' => true,
                    'status' => 'confirmed',
                    'customer' => [
                        'name' => 'Dimas Surya',
                        'phone' => '081234567891',
                    ],
                    'collateral' => [
                        'type' => 'KTP Asli',
                    ],
                    'financial' => [
                        'price' => 450000,
                        'deposit' => 200000,
                        'total_bill' => 650000,
                        'is_paid' => true,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'verification' => [
                    'checklist' => [
                        '*' => ['id', 'label', 'description', 'required'],
                    ],
                ],
            ]);

        // 2. Test via dedicated /api/v1/pickup/verify/{idOrCode}
        $pickupResponse = $this->getJson("/api/v1/pickup/verify/{$booking->booking_code}");
        $pickupResponse->assertStatus(200)
            ->assertJsonPath('can_pickup', true)
            ->assertJsonPath('verification.can_pickup', true);
    }

    public function test_booking_verification_rejects_pickup_if_already_rented(): void
    {
        $iphone = Iphones::factory()->create([
            'status' => 'rented',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909RENT',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Maya Anggraini',
            'customer_phone' => '081288776655',
            'start_booking_date' => Carbon::yesterday()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => Carbon::tomorrow()->toDateString(),
            'end_time' => '10:00',
            'duration' => 48,
            'price' => 450000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson("/api/v1/bookings/{$booking->booking_code}/verify");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'can_pickup' => false,
                'verification' => [
                    'can_pickup' => false,
                    'status' => 'rented',
                ],
            ]);

        $this->assertNotEmpty($response->json('verification.reasons'));
    }

    public function test_booking_verification_returns_404_for_invalid_code(): void
    {
        $response = $this->getJson('/api/v1/bookings/INVALID_NONEXISTENT_CODE/verify');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
