<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_sales_report_with_breakdowns(): void
    {
        $now = Carbon::now('Asia/Jakarta');

        $phone15 = Iphones::factory()->create(['name' => 'iPhone 15 Pro']);
        $phone14 = Iphones::factory()->create(['name' => 'iPhone 14']);

        $cash = Payment::create(['name' => 'Tunai Kasir', 'slug' => 'cash']);
        $qris = Payment::create(['name' => 'QRIS', 'slug' => 'qris']);

        // Booking 1 (Cash, iPhone 15 Pro)
        $b1 = Booking::create([
            'booking_code' => 'SKY-REP-01',
            'iphone_id' => $phone15->id,
            'customer_name' => 'Alif Ramadhan',
            'customer_phone' => '0811111111',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 500000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'payment_id' => $cash->id,
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $b1->id,
            'payment_id' => $cash->id,
            'amount' => 500000,
            'type' => 'payment',
            'paid_at' => $now,
        ]);

        // Booking 2 (QRIS, iPhone 14)
        $b2 = Booking::create([
            'booking_code' => 'SKY-REP-02',
            'iphone_id' => $phone14->id,
            'customer_name' => 'Bunga Citra',
            'customer_phone' => '0822222222',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDays(1)->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'payment_id' => $qris->id,
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $b2->id,
            'payment_id' => $qris->id,
            'amount' => 300000,
            'type' => 'payment',
            'paid_at' => $now,
        ]);

        // Request sales report
        $response = $this->getJson('/api/v1/reports/sales?period=Hari Ini');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'period' => 'Hari Ini',
                'summary' => [
                    'totalRevenue' => 800000,
                    'transactionCount' => 2,
                    'averageTransactionValue' => 400000,
                    'totalDepositsHeld' => 0,
                ],
            ])
            ->assertJsonStructure([
                'status',
                'period',
                'start_date',
                'end_date',
                'summary' => [
                    'totalRevenue',
                    'totalDepositsHeld',
                    'totalDepositsRefunded',
                    'transactionCount',
                    'averageTransactionValue',
                ],
                'paymentMethodBreakdown',
                'modelRentalCount',
                'modelRevenue',
                'transactions',
            ]);

        // Verify payment method breakdown
        $breakdown = $response->json('paymentMethodBreakdown');
        $this->assertEquals(500000, $breakdown['Tunai Kasir']);
        $this->assertEquals(300000, $breakdown['QRIS']);

        // Verify model breakdown
        $modelCount = $response->json('modelRentalCount');
        $this->assertEquals(1, $modelCount['iPhone 15 Pro']);
        $this->assertEquals(1, $modelCount['iPhone 14']);

        // Test alias endpoint /api/v1/dashboard/sales-report
        $aliasResponse = $this->getJson('/api/v1/dashboard/sales-report?period=Hari Ini');
        $aliasResponse->assertStatus(200)
            ->assertJsonFragment(['totalRevenue' => 800000]);
    }

    public function test_can_filter_report_by_payment_method(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $phone = Iphones::factory()->create(['name' => 'iPhone 13']);

        $cash = Payment::create(['name' => 'Tunai Kasir', 'slug' => 'cash']);
        $qris = Payment::create(['name' => 'QRIS', 'slug' => 'qris']);

        $b1 = Booking::create([
            'booking_code' => 'SKY-MTH-01',
            'iphone_id' => $phone->id,
            'customer_name' => 'Customer Cash',
            'customer_phone' => '08123',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDay()->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'rented',
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $b1->id,
            'payment_id' => $cash->id,
            'amount' => 250000,
            'type' => 'payment',
            'paid_at' => $now,
        ]);

        $b2 = Booking::create([
            'booking_code' => 'SKY-MTH-02',
            'iphone_id' => $phone->id,
            'customer_name' => 'Customer QRIS',
            'customer_phone' => '08124',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDay()->toDateString(),
            'duration' => 24,
            'price' => 250000,
            'status' => 'rented',
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $b2->id,
            'payment_id' => $qris->id,
            'amount' => 250000,
            'type' => 'payment',
            'paid_at' => $now,
        ]);

        // Filter only QRIS
        $response = $this->getJson('/api/v1/reports/sales?period=Hari Ini&payment_method=QRIS');

        $response->assertStatus(200)
            ->assertJson([
                'summary' => [
                    'totalRevenue' => 250000,
                    'transactionCount' => 1,
                ],
            ]);
    }

    public function test_can_filter_report_by_custom_date_range(): void
    {
        $phone = Iphones::factory()->create();
        $cash = Payment::create(['name' => 'Tunai Kasir', 'slug' => 'cash']);

        $b = Booking::create([
            'booking_code' => 'SKY-RANGE-01',
            'iphone_id' => $phone->id,
            'customer_name' => 'Range Customer',
            'customer_phone' => '0899999',
            'start_booking_date' => '2026-08-15',
            'end_booking_date' => '2026-08-17',
            'duration' => 48,
            'price' => 350000,
            'status' => 'returned',
            'created' => '2026-08-15 10:00:00',
        ]);

        BookingPayment::create([
            'booking_id' => $b->id,
            'payment_id' => $cash->id,
            'amount' => 350000,
            'type' => 'payment',
            'paid_at' => '2026-08-15 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/reports/sales?start_date=2026-08-01&end_date=2026-08-31');

        $response->assertStatus(200)
            ->assertJson([
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'summary' => [
                    'totalRevenue' => 350000,
                    'transactionCount' => 1,
                ],
            ]);
    }
}
