<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDailySummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_daily_dashboard_summary_with_all_metrics(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        // Create inventory
        $readyPhone = Iphones::factory()->create(['status' => 'ready']);
        $rentedPhone = Iphones::factory()->create(['status' => 'rented']);
        $maintPhone = Iphones::factory()->create(['status' => 'maintenance']);

        // 1. Active rental due today
        $activeDueToday = Booking::create([
            'booking_code' => 'SKY-DASH-01',
            'iphone_id' => $rentedPhone->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '0811122233',
            'start_booking_date' => $today->copy()->subDays(2)->toDateString(),
            'end_booking_date' => $today->toDateString(),
            'end_time' => '23:59',
            'duration' => 48,
            'price' => 500000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 2. Overdue rental
        $overdueBooking = Booking::create([
            'booking_code' => 'SKY-DASH-02',
            'iphone_id' => $rentedPhone->id,
            'customer_name' => 'Doni Saputra',
            'customer_phone' => '0822233344',
            'start_booking_date' => $today->copy()->subDays(4)->toDateString(),
            'end_booking_date' => $today->copy()->subDays(1)->toDateString(),
            'duration' => 72,
            'price' => 700000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // 3. Confirmed pickup scheduled for today
        $pickupToday = Booking::create([
            'booking_code' => 'SKY-DASH-03',
            'iphone_id' => $readyPhone->id,
            'customer_name' => 'Rian Hidayat',
            'customer_phone' => '0833344455',
            'start_booking_date' => $today->toDateString(),
            'end_booking_date' => $today->copy()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 400000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $paymentMethod = \App\Models\Payment::create([
            'name' => 'Cash',
            'slug' => 'cash',
        ]);

        BookingPayment::create([
            'booking_id' => $pickupToday->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 400000,
            'type' => 'payment',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'date' => $today->toDateString(),
                'metrics' => [
                    'activeRentals' => 2,
                    'todayPickups' => 1,
                    'todayReturns' => 1,
                    'overdueReturns' => 1,
                    'totalUnits' => 3,
                    'rentedUnits' => 1,
                    'availableUnits' => 1,
                    'maintenanceUnits' => 1,
                ],
            ])
            ->assertJsonStructure([
                'status',
                'date',
                'metrics' => [
                    'activeRentals',
                    'todayPickups',
                    'todayReturns',
                    'overdueReturns',
                    'availableUnits',
                    'rentedUnits',
                    'maintenanceUnits',
                    'totalUnits',
                    'utilizationRate',
                ],
                'financials' => [
                    'totalRevenue',
                    'totalHeldDeposit',
                    'totalRefundedDeposit',
                ],
                'actionItems',
                'recentBookings',
            ]);

        $this->assertEquals(400000, $response->json('financials.totalRevenue'));
        $this->assertEquals(0, $response->json('financials.totalHeldDeposit'));
        $this->assertNotEmpty($response->json('actionItems'));
        $this->assertNotEmpty($response->json('recentBookings'));
    }

    public function test_can_access_dashboard_via_alias_endpoints(): void
    {
        $respDaily = $this->getJson('/api/v1/dashboard/daily');
        $respDaily->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $respIndex = $this->getJson('/api/v1/dashboard');
        $respIndex->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    public function test_can_specify_custom_date(): void
    {
        $customDate = '2026-10-15';
        $phone = Iphones::factory()->create();

        Booking::create([
            'booking_code' => 'SKY-FUTURE-01',
            'iphone_id' => $phone->id,
            'customer_name' => 'Future Customer',
            'customer_phone' => '081234567899',
            'start_booking_date' => $customDate,
            'end_booking_date' => '2026-10-18',
            'duration' => 72,
            'price' => 600000,
            'status' => 'confirmed',
            'created' => now(),
        ]);

        $response = $this->getJson("/api/v1/dashboard/summary?date={$customDate}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'date' => $customDate,
                'metrics' => [
                    'todayPickups' => 1,
                ],
            ]);
    }
}
