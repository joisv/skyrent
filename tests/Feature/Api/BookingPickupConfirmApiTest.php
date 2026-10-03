<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPickupConfirmApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_confirm_pickup_with_currently_assigned_iphone(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-PICKUP',
            'asset_code' => 'AST-IP15P-001',
            'status' => 'ready',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909CONFIRM',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Dimas Surya',
            'customer_phone' => '081234567891',
            'customer_email' => 'dimas@example.com',
            'address' => 'Jl. Kaliurang KM 5, Sleman',
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

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/pickup", [
            'jaminan_type' => 'KTP Asli',
            'picked_up_by' => 99,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909CONFIRM',
                    'status' => 'confirmed',
                ],
                'handover_summary' => [
                    'booking_code' => 'SKY260909CONFIRM',
                    'customer_name' => 'Dimas Surya',
                    'collateral_type' => 'KTP Asli',
                    'collateral_received' => true,
                    'picked_up_by' => 99,
                ],
            ]);

        // Verify booking in DB
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);

        // Verify iPhone in DB
        $iphone->refresh();
        $this->assertEquals('rented', $iphone->status);
    }

    public function test_can_confirm_pickup_and_assign_different_available_iphone(): void
    {
        $oldIphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-OLD',
            'asset_code' => 'AST-IP15P-OLD',
            'status' => 'booked',
        ]);

        $newIphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-NEW',
            'asset_code' => 'AST-IP15P-NEW',
            'status' => 'ready',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909REASSIGN',
            'iphone_id' => $oldIphone->id,
            'customer_name' => 'Ahmad Rian',
            'customer_phone' => '081234567892',
            'pickup_type' => 'Outlet',
            'jaminan_type' => 'SIM A',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Post to alias /api/v1/pickup/confirm
        $response = $this->postJson('/api/v1/pickup/confirm', [
            'booking_code' => 'SKY260909REASSIGN',
            'iphone_id' => $newIphone->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909REASSIGN',
                    'status' => 'confirmed',
                ],
                'handover_summary' => [
                    'asset_code' => 'AST-IP15P-NEW',
                ],
            ]);

        $booking->refresh();
        $this->assertEquals($newIphone->id, $booking->iphone_id);
        $this->assertEquals('confirmed', $booking->status);

        // New iPhone should now be rented
        $newIphone->refresh();
        $this->assertEquals('rented', $newIphone->status);

        // Old iPhone should be released back to ready
        $oldIphone->refresh();
        $this->assertEquals('ready', $oldIphone->status);
    }

    public function test_cannot_confirm_pickup_with_unavailable_iphone(): void
    {
        $baseIphone = Iphones::factory()->create(['status' => 'ready']);
        $busyIphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro',
            'serial_number' => 'SN-IP15P-BUSY',
            'asset_code' => 'AST-IP15P-BUSY',
            'status' => 'maintenance',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909UNAVAIL',
            'iphone_id' => $baseIphone->id,
            'customer_name' => 'Bayu',
            'customer_phone' => '081234567893',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'confirmed',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/pickup", [
            'iphone_id' => $busyIphone->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_cannot_confirm_pickup_if_already_rented(): void
    {
        $iphone = Iphones::factory()->create(['status' => 'rented']);
        $booking = Booking::create([
            'booking_code' => 'SKY260909RENTED',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Citra',
            'customer_phone' => '081234567894',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'rented',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/pickup");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unit iPhone untuk booking ini sudah diserahkan (status: Sedang Sewa).',
            ]);
    }

    public function test_cannot_confirm_pickup_if_returned_or_cancelled(): void
    {
        $iphone1 = Iphones::factory()->create(['status' => 'ready']);
        $returnedBooking = Booking::create([
            'booking_code' => 'SKY260909RET',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Dewi',
            'customer_phone' => '081234567895',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'returned',
            'created' => now(),
        ]);

        $iphone2 = Iphones::factory()->create(['status' => 'ready']);
        $cancelledBooking = Booking::create([
            'booking_code' => 'SKY260909CAN',
            'iphone_id' => $iphone2->id,
            'customer_name' => 'Eko',
            'customer_phone' => '081234567896',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'cancelled',
            'created' => now(),
        ]);

        $this->postJson("/api/v1/bookings/{$returnedBooking->booking_code}/pickup")
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Booking ini sudah selesai dikembalikan.',
            ]);

        $this->postJson("/api/v1/bookings/{$cancelledBooking->booking_code}/pickup")
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Booking ini telah dibatalkan.',
            ]);
    }

    public function test_confirm_pickup_returns_404_for_invalid_code(): void
    {
        $response = $this->postJson('/api/v1/bookings/NON-EXISTENT/pickup');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
