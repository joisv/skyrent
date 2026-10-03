<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_dp_payment_and_update_status_to_partial(): void
    {
        $iphone = Iphones::factory()->create();
        $cashPayment = Payment::create(['name' => 'Tunai (Cash)', 'slug' => 'cash', 'is_active' => true]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909PAYDP',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Aditya Pratama',
            'customer_phone' => '081234567811',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 500000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 200000,
            'payment_method' => 'cash',
            'note' => 'DP Sewa 2 hari',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909PAYDP',
                    'payment_status' => 'partial',
                    'total_paid' => 200000,
                    'remaining_payment' => 300000,
                ],
                'payment' => [
                    'amount' => 200000,
                    'type' => 'dp',
                    'payment_method' => 'Tunai (Cash)',
                ],
                'payment_summary' => [
                    'price' => 500000,
                    'amount_paid' => 200000,
                    'total_paid' => 200000,
                    'remaining_payment' => 300000,
                    'payment_status' => 'partial',
                    'is_settled' => false,
                ],
            ]);

        $booking->refresh();
        $this->assertEquals('partial', $booking->payment_status);
        $this->assertEquals(200000, (float) $booking->total_paid);
        $this->assertEquals(300000, (float) $booking->remaining_payment);
    }

    public function test_can_record_full_payment_and_update_status_to_paid(): void
    {
        $iphone = Iphones::factory()->create();
        $qrisPayment = Payment::create(['name' => 'QRIS', 'slug' => 'qris', 'is_active' => true]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909PAYFULL',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Bagus Santika',
            'customer_phone' => '081234567822',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 300000,
            'pay' => 350000,
            'payment_id' => $qrisPayment->id,
            'reference_number' => 'QRIS-REF-998811',
            'note' => 'Pembayaran lunas via QRIS',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'payment_status' => 'paid',
                    'total_paid' => 300000,
                    'remaining_payment' => 0,
                ],
                'payment' => [
                    'amount' => 300000,
                    'pay' => 350000,
                    'change' => 50000,
                ],
                'payment_summary' => [
                    'is_settled' => true,
                    'payment_status' => 'paid',
                    'remaining_payment' => 0,
                ],
            ]);

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals(300000, (float) $booking->total_paid);
        $this->assertEquals(0, (float) $booking->remaining_payment);
    }

    public function test_can_pay_remaining_balance_after_dp(): void
    {
        $iphone = Iphones::factory()->create();
        $transferPayment = Payment::create(['name' => 'Transfer Bank', 'slug' => 'transfer', 'is_active' => true]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909SETTLE',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Cindy Clarissa',
            'customer_phone' => '081234567833',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 400000,
            'total_paid' => 150000,
            'remaining_payment' => 250000,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'created' => now(),
        ]);

        // Existing DP record
        BookingPayment::create([
            'payment_code' => 'PAY-INIT-001',
            'booking_id' => $booking->id,
            'payment_id' => $transferPayment->id,
            'amount' => 150000,
            'pay' => 150000,
            'change' => 0,
            'type' => 'dp',
            'paid_at' => now()->subDay(),
        ]);

        // Pay remaining 250,000 via /api/v1/payments/rental
        $response = $this->postJson('/api/v1/payments/rental', [
            'booking_code' => $booking->booking_code,
            'amount' => 250000,
            'payment_method' => 'transfer',
            'note' => 'Pelunasan sisa sewa di outlet',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'payment_status' => 'paid',
                    'total_paid' => 400000,
                    'remaining_payment' => 0,
                ],
                'payment_summary' => [
                    'total_paid' => 400000,
                    'remaining_payment' => 0,
                    'is_settled' => true,
                ],
            ]);

        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals(400000, (float) $booking->total_paid);
        $this->assertEquals(0, (float) $booking->remaining_payment);
    }

    public function test_cannot_pay_for_already_fully_paid_booking(): void
    {
        $iphone = Iphones::factory()->create();
        $cashPayment = Payment::create(['name' => 'Tunai', 'slug' => 'cash', 'is_active' => true]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909PAIDALREADY',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Deni',
            'customer_phone' => '081234567844',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-FULL-001',
            'booking_id' => $booking->id,
            'payment_id' => $cashPayment->id,
            'amount' => 300000,
            'pay' => 300000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 100000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Tagihan sewa untuk booking ini sudah lunas (Rp 300.000).',
            ]);
    }

    public function test_cannot_pay_for_cancelled_booking(): void
    {
        $iphone = Iphones::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'SKY260909CANCEL',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Eka',
            'customer_phone' => '081234567855',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 300000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Tidak dapat melakukan pembayaran untuk booking yang telah dibatalkan.',
            ]);
    }

    public function test_payment_rejects_pay_less_than_amount(): void
    {
        $iphone = Iphones::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'SKY260909UNDERPAY',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Fani',
            'customer_phone' => '081234567866',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 300000,
            'pay' => 200000, // less than amount
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Jumlah uang yang dibayarkan (pay) tidak boleh lebih kecil dari tagihan (amount).',
            ]);
    }

    public function test_payment_returns_404_for_invalid_booking_code(): void
    {
        $response = $this->postJson('/api/v1/bookings/INVALID-CODE/payments', [
            'amount' => 100000,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
