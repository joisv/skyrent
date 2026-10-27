<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CashDrawerShift;
use App\Models\Duration;
use App\Models\Gallery;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddonBookingAndSalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Affiliate $affiliate;
    protected Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->affiliate = Affiliate::create([
            'id' => 4,
            'code' => 'PST',
            'name' => 'Genteng Pusat',
            'slug' => 'genteng-pusat',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'affiliate_id' => $this->affiliate->id,
        ]);
        $this->user->assignRole('super-admin');
        Sanctum::actingAs($this->user);

        $this->gallery = Gallery::create([
            'id' => 1,
            'image' => 'default.jpg',
        ]);
    }

    public function test_booking_store_with_addons_saves_addon_devices_and_updates_price(): void
    {
        $duration = Duration::create(['hours' => 24, 'name' => '24 Jam']);

        $iphone = Iphones::create([
            'serial_number' => 'TESTIPHONE15PRO',
            'name' => 'iPhone 15 Pro Test',
            'status' => 'ready',
            'affiliate_id' => $this->affiliate->id,
            'asset_code' => 'IPH15P-01',
            'slug' => 'iphone-15-pro-test',
            'user_id' => $this->user->id,
            'gallery_id' => $this->gallery->id,
        ]);
        $iphone->durations()->attach($duration->id, ['price' => 150000]);

        $addon = Iphones::create([
            'serial_number' => 'ACC-TRIPOD-TEST',
            'name' => 'Tripod Mini Test',
            'status' => 'ready',
            'affiliate_id' => null,
            'asset_code' => 'ADDON-TRIPOD-01',
            'slug' => 'tripod-mini-test',
            'user_id' => $this->user->id,
            'gallery_id' => $this->gallery->id,
        ]);
        $addon->durations()->attach($duration->id, ['price' => 25000]);

        $response = $this->postJson('/api/v1/bookings', [
            'customer_name' => 'Budi Tester',
            'customer_phone' => '081234567890',
            'iphone_id' => $iphone->id,
            'start_booking_date' => Carbon::now()->addDays(1)->toDateString(),
            'end_booking_date' => Carbon::now()->addDays(2)->toDateString(),
            'duration' => 24,
            'price' => 175000,
            'addon_asset_ids' => [$addon->id],
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals(175000, (float) $data['price']);
        $this->assertEquals(25000, (float) $data['addon_subtotal']);
        $this->assertEquals(150000, (float) $data['primary_subtotal']);
        $this->assertNotEmpty($data['addon_devices']);
        $this->assertEquals($addon->id, $data['addon_devices'][0]['id']);
        $this->assertEquals('Tripod Mini Test', $data['addon_devices'][0]['name']);
    }

    public function test_shift_history_returns_flow_metrics_and_timestamps(): void
    {
        $shift = CashDrawerShift::create([
            'shift_number' => 'SHF-TEST-FLOW-01',
            'branch_id' => $this->affiliate->id,
            'opened_by' => (string) $this->user->id,
            'opened_at' => Carbon::now()->subHours(4),
            'closed_at' => Carbon::now()->subHour(),
            'opening_cash_float' => 100000,
            'expected_cash_total' => 250000,
            'actual_cash_counted' => 250000,
            'cash_difference' => 0,
            'status' => 'closed',
        ]);

        $response = $this->getJson('/api/v1/cashier/shift/history');
        $response->assertStatus(200);

        $shiftData = collect($response->json('data'))->firstWhere('shift_number', 'SHF-TEST-FLOW-01');
        $this->assertNotNull($shiftData);
        $this->assertNotNull($shiftData['opened_at']);
        $this->assertNotNull($shiftData['closed_at']);
        $this->assertArrayHasKey('current_cash_inflow', $shiftData);
        $this->assertArrayHasKey('current_cash_expenses', $shiftData);
    }
}
