<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptDetailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_receipt_detail_by_booking_code(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max 256GB',
            'serial_number' => 'SN15PM-998877',
            'asset_code' => 'AST-IP15PM-01',
        ]);
        $cashPayment = Payment::create(['name' => 'Tunai (Cash)', 'slug' => 'cash', 'is_active' => true]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909REC01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'pickup_type' => 'Outlet',
            'start_booking_date' => Carbon::today()->toDateString(),
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 600000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $cashPayment->id,
            'amount' => 600000,
            'pay' => 600000,
            'type' => 'payment',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/receipts/' . $booking->booking_code);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909REC01',
                    'customer_name' => 'Budi Santoso',
                    'customer_phone' => '081234567890',
                    'unit_name' => 'iPhone 15 Pro Max 256GB',
                    'serial_number' => 'SN15PM-998877',
                    'asset_code' => 'AST-IP15PM-01',
                    'rent_fee' => 600000,
                    'deposit_fee' => 0,
                    'total_amount' => 600000,
                    'type' => 'pickup',
                    'type_label' => 'PICKUP IPHONE',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.esc_pos_text'));
        $this->assertNotEmpty($response->json('data.receipt_number'));
    }

    public function test_can_get_receipt_detail_via_alias_route(): void
    {
        $iphone = Iphones::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'SKY260909REC02',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '089876543210',
            'duration' => 24,
            'price' => 450000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson("/api/v1/bookings/{$booking->id}/receipt");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'booking_code' => 'SKY260909REC02',
                    'customer_name' => 'Dewi Lestari',
                    'rent_fee' => 450000,
                ],
            ]);
    }

    public function test_can_specify_receipt_type(): void
    {
        $iphone = Iphones::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'SKY260909REC03',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Citra Kirana',
            'customer_phone' => '081333444555',
            'duration' => 24,
            'price' => 400000,
            'status' => 'returned',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Query param ?type=returnUnit
        $respReturn = $this->getJson("/api/v1/receipts/{$booking->booking_code}?type=returnUnit");
        $respReturn->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'type' => 'returnUnit',
                    'type_label' => 'PENGEMBALIAN UNIT',
                ],
            ]);

        // Query param ?type=depositRefund
        $respRefund = $this->getJson("/api/v1/receipts/{$booking->booking_code}?type=depositRefund");
        $respRefund->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'type' => 'depositRefund',
                    'type_label' => 'REFUND DEPOSIT',
                ],
            ]);
    }

    public function test_receipt_esc_pos_text_lines_do_not_exceed_32_chars(): void
    {
        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max Deep Purple 1TB Super Long Name',
            'serial_number' => 'SN-VERY-LONG-SERIAL-NUMBER-12345678',
            'asset_code' => 'AST-LONG-CODE-99999',
        ]);

        $booking = Booking::create([
            'booking_code' => 'SKY260909REC04',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Muhammad Sangat Panjang Nama Sekali Hendra Wijaya',
            'customer_phone' => '0812345678901234',
            'duration' => 24,
            'price' => 1250000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->getJson("/api/v1/receipts/{$booking->booking_code}");

        $response->assertStatus(200);

        $escPosText = $response->json('data.esc_pos_text');
        $this->assertNotEmpty($escPosText);

        $lines = explode("\n", $escPosText);
        foreach ($lines as $index => $line) {
            $this->assertLessThanOrEqual(
                32,
                strlen($line),
                "Line {$index} exceeds 32 chars: '{$line}' (len: " . strlen($line) . ")"
            );
        }
    }

    public function test_returns_404_when_booking_not_found(): void
    {
        $response = $this->getJson('/api/v1/receipts/NON_EXISTENT_CODE');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
