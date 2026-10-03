<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $staffA;
    protected User $staffB;
    protected Iphones $iphone;
    protected Payment $cashPayment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'affiliate-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['name' => 'Admin Cabang']);
        $this->admin->assignRole('admin');

        $this->staffA = User::factory()->create(['name' => 'Staff A']);
        $this->staffA->assignRole('staff');

        $this->staffB = User::factory()->create(['name' => 'Staff B']);
        $this->staffB->assignRole('staff');

        $this->iphone = Iphones::factory()->create([
            'status' => 'ready',
        ]);

        $this->cashPayment = Payment::firstOrCreate(
            ['slug' => 'cash'],
            ['name' => 'Tunai (Cash)', 'is_active' => true]
        );
    }

    public function test_staff_only_sees_their_own_bookings_in_index(): void
    {
        $bookingA = Booking::factory()->create([
            'user_id' => $this->staffA->id,
            'customer_name' => 'Customer of Staff A',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'customer_name' => 'Customer of Staff B',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        // Staff A index
        Sanctum::actingAs($this->staffA);
        $resA = $this->getJson('/api/v1/bookings');
        $resA->assertStatus(200);
        $idsA = collect($resA->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingA->id, $idsA);
        $this->assertNotContains($bookingB->id, $idsA);

        // Staff B index
        Sanctum::actingAs($this->staffB);
        $resB = $this->getJson('/api/v1/bookings');
        $resB->assertStatus(200);
        $idsB = collect($resB->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingB->id, $idsB);
        $this->assertNotContains($bookingA->id, $idsB);
    }

    public function test_staff_only_sees_their_own_bookings_in_today_summary(): void
    {
        $today = Carbon::today()->toDateString();

        $bookingA = Booking::factory()->create([
            'user_id' => $this->staffA->id,
            'customer_name' => 'Pickup Staff A',
            'start_booking_date' => $today,
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'customer_name' => 'Pickup Staff B',
            'start_booking_date' => $today,
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($this->staffA);
        $res = $this->getJson('/api/v1/bookings/today');
        $res->assertStatus(200);
        $pickupIds = collect($res->json('data.pickups'))->pluck('id')->toArray();

        $this->assertContains($bookingA->id, $pickupIds);
        $this->assertNotContains($bookingB->id, $pickupIds);
    }

    public function test_staff_only_sees_their_own_queue_in_dashboard_summary(): void
    {
        $today = Carbon::today()->toDateString();

        $bookingA = Booking::factory()->create([
            'user_id' => $this->staffA->id,
            'customer_name' => 'Queue Staff A',
            'start_booking_date' => $today,
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'customer_name' => 'Queue Staff B',
            'start_booking_date' => $today,
            'end_booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($this->staffA);
        $res = $this->getJson('/api/v1/dashboard/summary');
        $res->assertStatus(200);
        $queueIds = collect($res->json('all_bookings'))->pluck('id')->toArray();

        $this->assertContains($bookingA->id, $queueIds);
        $this->assertNotContains($bookingB->id, $queueIds);
    }

    public function test_staff_cannot_access_or_modify_other_staff_booking(): void
    {
        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'customer_name' => 'Booking Staff B',
            'price' => 200000,
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        Sanctum::actingAs($this->staffA);

        // Staff A tries to view Staff B booking
        $resView = $this->getJson("/api/v1/bookings/{$bookingB->id}");
        $resView->assertStatus(403);

        // Staff A tries to delete Staff B booking
        $resDelete = $this->deleteJson("/api/v1/bookings/{$bookingB->id}");
        $resDelete->assertStatus(403);

        // Staff A tries to pay for Staff B booking
        $resPay = $this->postJson("/api/v1/bookings/{$bookingB->id}/payments", [
            'amount' => 50000,
        ]);
        $resPay->assertStatus(403);
    }

    public function test_backend_stamps_authenticated_user_id_ignoring_client_payload(): void
    {
        $startDate = Carbon::today()->addDays(5)->format('Y-m-d');
        $endDate = Carbon::today()->addDays(7)->format('Y-m-d');

        Sanctum::actingAs($this->staffA);

        $response = $this->postJson('/api/v1/bookings', [
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Client Spoof Test',
            'customer_phone' => '081234567890',
            'start_booking_date' => $startDate,
            'end_booking_date' => $endDate,
            'price' => 250000,
            'user_id' => $this->staffB->id, // Client spoofing Staff B id
        ]);

        $response->assertStatus(201);
        $createdBookingId = $response->json('data.id');

        $booking = Booking::find($createdBookingId);
        $this->assertNotNull($booking);
        // Must be stamped with Staff A (the authenticated user), NOT Staff B
        $this->assertEquals($this->staffA->id, $booking->user_id);
    }

    public function test_super_admin_and_admin_can_see_all_bookings(): void
    {
        $bookingA = Booking::factory()->create([
            'user_id' => $this->staffA->id,
            'customer_name' => 'Booking Staff A',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'customer_name' => 'Booking Staff B',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        // Super Admin sees both
        Sanctum::actingAs($this->superAdmin);
        $resSuper = $this->getJson('/api/v1/bookings');
        $resSuper->assertStatus(200);
        $superIds = collect($resSuper->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingA->id, $superIds);
        $this->assertContains($bookingB->id, $superIds);

        // Super Admin can view Staff B detail
        $resSuperDetail = $this->getJson("/api/v1/bookings/{$bookingB->id}");
        $resSuperDetail->assertStatus(200);

        // General Admin sees both
        Sanctum::actingAs($this->admin);
        $resAdmin = $this->getJson('/api/v1/bookings');
        $resAdmin->assertStatus(200);
        $adminIds = collect($resAdmin->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingA->id, $adminIds);
        $this->assertContains($bookingB->id, $adminIds);
    }

    public function test_staff_sales_report_only_includes_their_own_revenue(): void
    {
        $now = Carbon::now('Asia/Jakarta');

        $bookingA = Booking::factory()->create([
            'user_id' => $this->staffA->id,
            'price' => 300000,
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingB = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'price' => 500000,
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        BookingPayment::create([
            'booking_id' => $bookingA->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 300000,
            'pay' => 300000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $now,
            'user_id' => $this->staffA->id,
        ]);

        BookingPayment::create([
            'booking_id' => $bookingB->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 500000,
            'pay' => 500000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $now,
            'user_id' => $this->staffB->id,
        ]);

        Sanctum::actingAs($this->staffA);
        $resStaff = $this->getJson('/api/v1/dashboard/sales-report?period=today');
        $resStaff->assertStatus(200);

        // Staff revenue must be 300000, not 800000
        $this->assertEquals(300000, (float) $resStaff->json('summary.total_revenue'));
        // Staff should not have affiliate breakdown
        $this->assertEmpty($resStaff->json('affiliate_breakdown'));

        // Super Admin sees all revenue (800000)
        Sanctum::actingAs($this->superAdmin);
        $resAdmin = $this->getJson('/api/v1/dashboard/sales-report?period=today');
        $resAdmin->assertStatus(200);
        $this->assertEquals(800000, (float) $resAdmin->json('summary.total_revenue'));
    }

    public function test_affiliate_admin_only_sees_their_affiliate_bookings_in_index(): void
    {
        $affiliateA = Affiliate::create([
            'name' => 'SkyRental Solo',
            'code' => 'SOLO',
            'slug' => 'skyrental-solo',
            'is_active' => true,
        ]);

        $affiliateB = Affiliate::create([
            'name' => 'SkyRental Semarang',
            'code' => 'SMG',
            'slug' => 'skyrental-semarang',
            'is_active' => true,
        ]);

        $affiliateAdminA = User::factory()->create([
            'name' => 'Admin Solo',
            'affiliate_id' => $affiliateA->id,
        ]);
        $affiliateAdminA->assignRole('affiliate-admin');

        $bookingA = Booking::factory()->create([
            'affiliate_id' => $affiliateA->id,
            'customer_name' => 'Pelanggan Solo',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingB = Booking::factory()->create([
            'affiliate_id' => $affiliateB->id,
            'customer_name' => 'Pelanggan Semarang',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        Sanctum::actingAs($affiliateAdminA);
        $res = $this->getJson('/api/v1/bookings');
        $res->assertStatus(200);

        $bookingIds = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingA->id, $bookingIds);
        $this->assertNotContains($bookingB->id, $bookingIds);
    }

    public function test_affiliate_admin_cannot_access_or_modify_other_affiliate_booking(): void
    {
        $affiliateA = Affiliate::create([
            'name' => 'SkyRental Bali',
            'code' => 'DPS',
            'slug' => 'skyrental-bali',
            'is_active' => true,
        ]);

        $affiliateB = Affiliate::create([
            'name' => 'SkyRental Surabaya',
            'code' => 'SUB',
            'slug' => 'skyrental-surabaya',
            'is_active' => true,
        ]);

        $affiliateAdminA = User::factory()->create([
            'name' => 'Admin Bali',
            'affiliate_id' => $affiliateA->id,
        ]);
        $affiliateAdminA->assignRole('affiliate-admin');

        $bookingB = Booking::factory()->create([
            'affiliate_id' => $affiliateB->id,
            'customer_name' => 'Pelanggan Surabaya',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        Sanctum::actingAs($affiliateAdminA);

        // Cannot view other affiliate booking
        $this->getJson("/api/v1/bookings/{$bookingB->id}")
            ->assertStatus(403);

        // Cannot delete other affiliate booking
        $this->deleteJson("/api/v1/bookings/{$bookingB->id}")
            ->assertStatus(403);

        // Cannot record payment for other affiliate booking
        $this->postJson("/api/v1/bookings/{$bookingB->id}/payments", [
            'amount' => 100000,
        ])->assertStatus(403);
    }

    public function test_affiliate_admin_sales_report_only_includes_their_affiliate_revenue_and_hides_affiliate_breakdown(): void
    {
        $now = Carbon::now('Asia/Jakarta');

        $affiliateA = Affiliate::create([
            'name' => 'SkyRental Bandung',
            'code' => 'BDG',
            'slug' => 'skyrental-bandung',
            'is_active' => true,
        ]);

        $affiliateB = Affiliate::create([
            'name' => 'SkyRental Medan',
            'code' => 'KNO',
            'slug' => 'skyrental-medan',
            'is_active' => true,
        ]);

        $affiliateAdminA = User::factory()->create([
            'name' => 'Admin Bandung',
            'affiliate_id' => $affiliateA->id,
        ]);
        $affiliateAdminA->assignRole('affiliate-admin');

        $bookingA = Booking::factory()->create([
            'affiliate_id' => $affiliateA->id,
            'price' => 450000,
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingB = Booking::factory()->create([
            'affiliate_id' => $affiliateB->id,
            'price' => 750000,
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        BookingPayment::create([
            'booking_id' => $bookingA->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 450000,
            'pay' => 450000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $now,
            'user_id' => $affiliateAdminA->id,
        ]);

        BookingPayment::create([
            'booking_id' => $bookingB->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 750000,
            'pay' => 750000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $now,
            'user_id' => $this->staffB->id,
        ]);

        Sanctum::actingAs($affiliateAdminA);
        $res = $this->getJson('/api/v1/dashboard/sales-report?period=today');
        $res->assertStatus(200);

        // Revenue must only be Bandung revenue (450000), not total (1200000)
        $this->assertEquals(450000, (float) $res->json('summary.total_revenue'));
        // Affiliate breakdown must be hidden
        $this->assertEmpty($res->json('affiliate_breakdown'));
    }

    public function test_affiliate_admin_without_affiliate_id_only_sees_their_own_created_bookings(): void
    {
        $unassignedAdmin = User::factory()->create([
            'name' => 'Unassigned Affiliate Admin',
            'affiliate_id' => null,
        ]);
        $unassignedAdmin->assignRole('affiliate-admin');

        $bookingCreatedByAdmin = Booking::factory()->create([
            'user_id' => $unassignedAdmin->id,
            'affiliate_id' => null,
            'customer_name' => 'Booking by unassigned',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        $bookingOther = Booking::factory()->create([
            'user_id' => $this->staffB->id,
            'affiliate_id' => null,
            'customer_name' => 'Other Booking',
            'start_time' => '10:00:00',
            'end_time' => '10:00:00',
        ]);

        Sanctum::actingAs($unassignedAdmin);
        $res = $this->getJson('/api/v1/bookings');
        $res->assertStatus(200);

        $bookingIds = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($bookingCreatedByAdmin->id, $bookingIds);
        $this->assertNotContains($bookingOther->id, $bookingIds);
    }
}

