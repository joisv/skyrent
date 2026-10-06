<?php

namespace Tests\Feature;

use App\Livewire\Affiliate\Revenue;
use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Gallery;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\ShopSetting;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateRevenueScopeAndDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShopSettingsSeeder::class);

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'affiliate-admin']);
        Role::firstOrCreate(['name' => 'affiliate']);
    }

    public function test_affiliate_admin_sees_revenue_created_by_super_admin_for_their_affiliate(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $userAffA = User::factory()->create(['affiliate_id' => $affA->id]);
        $userAffA->assignRole('affiliate-admin');

        $paymentMethod = Payment::create(['name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);

        $gallery = Gallery::create(['image' => 'iphone.jpg']);
        $iphoneA = Iphones::create([
            'name' => 'iPhone 13 Starlight',
            'slug' => 'iphone-13-starlight',
            'serial_number' => 'ABC1234567',
            'asset_code' => 'AST-IP13-001',
            'gallery_id' => $gallery->id,
            'user_id' => $superAdmin->id,
            'status' => 'ready',
            'affiliate_id' => $affA->id,
        ]);

        $bookingA = Booking::create([
            'iphone_id' => $iphoneA->id,
            'affiliate_id' => $affA->id,
            'user_id' => $superAdmin->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '0811111111',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 500000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYTEST001',
            'payment_id' => $paymentMethod->id,
            'payment_status' => 'paid',
        ]);

        // Revenue created by super-admin for Affiliate A
        BookingPayment::create([
            'booking_id' => $bookingA->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 500000,
            'pay' => 500000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $superAdmin->id,
            'note' => 'Payment processed by super-admin',
        ]);

        // Authenticated as Affiliate A admin
        $this->actingAs($userAffA);

        Livewire::test(Revenue::class)
            ->assertSet('affiliateRevenue', 500000.0)
            ->assertSet('affiliateBookingCount', 1)
            ->assertSet('revenueToday', 500000.0)
            ->assertSee('SKYTEST001')
            ->assertSee('John Doe')
            ->assertSee('500.000');
    }

    public function test_affiliate_admin_does_not_see_revenue_for_another_affiliate_even_if_created_by_super_admin(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $userAffB = User::factory()->create(['affiliate_id' => $affB->id]);
        $userAffB->assignRole('affiliate-admin');

        $paymentMethod = Payment::create(['name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);

        $gallery = Gallery::create(['image' => 'iphone.jpg']);
        $iphoneA = Iphones::create([
            'name' => 'iPhone 13 Starlight',
            'slug' => 'iphone-13-starlight',
            'serial_number' => 'ABC1234567',
            'asset_code' => 'AST-IP13-001',
            'gallery_id' => $gallery->id,
            'user_id' => $superAdmin->id,
            'status' => 'ready',
            'affiliate_id' => $affA->id,
        ]);

        $bookingA = Booking::create([
            'iphone_id' => $iphoneA->id,
            'affiliate_id' => $affA->id,
            'user_id' => $superAdmin->id,
            'customer_name' => 'Customer A',
            'customer_phone' => '0811111111',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 500000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYTEST001',
            'payment_id' => $paymentMethod->id,
            'payment_status' => 'paid',
        ]);

        BookingPayment::create([
            'booking_id' => $bookingA->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 500000,
            'pay' => 500000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $superAdmin->id,
        ]);

        // Authenticated as Affiliate B admin
        $this->actingAs($userAffB);

        Livewire::test(Revenue::class)
            ->assertSet('affiliateRevenue', 0.0)
            ->assertSet('affiliateBookingCount', 0)
            ->assertSet('revenueToday', 0.0)
            ->assertDontSee('SKYTEST001')
            ->assertDontSee('Customer A');
    }

    public function test_super_admin_can_see_all_affiliate_revenue(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $paymentMethod = Payment::create(['name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);

        $gallery = Gallery::create(['image' => 'iphone.jpg']);
        $iphoneA = Iphones::create([
            'name' => 'iPhone A',
            'slug' => 'iphone-a',
            'serial_number' => 'AAA111',
            'asset_code' => 'AST-001',
            'gallery_id' => $gallery->id,
            'user_id' => $superAdmin->id,
            'status' => 'ready',
            'affiliate_id' => $affA->id,
        ]);
        $iphoneB = Iphones::create([
            'name' => 'iPhone B',
            'slug' => 'iphone-b',
            'serial_number' => 'BBB222',
            'asset_code' => 'AST-002',
            'gallery_id' => $gallery->id,
            'user_id' => $superAdmin->id,
            'status' => 'ready',
            'affiliate_id' => $affB->id,
        ]);

        $bookingA = Booking::create([
            'iphone_id' => $iphoneA->id,
            'affiliate_id' => $affA->id,
            'user_id' => $superAdmin->id,
            'customer_name' => 'Customer Alpha',
            'customer_phone' => '0811111111',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 200000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYALPHA',
            'payment_id' => $paymentMethod->id,
        ]);

        $bookingB = Booking::create([
            'iphone_id' => $iphoneB->id,
            'affiliate_id' => $affB->id,
            'user_id' => $superAdmin->id,
            'customer_name' => 'Customer Beta',
            'customer_phone' => '0822222222',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 300000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYBETA',
            'payment_id' => $paymentMethod->id,
        ]);

        BookingPayment::create([
            'booking_id' => $bookingA->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 200000,
            'pay' => 200000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $superAdmin->id,
        ]);

        BookingPayment::create([
            'booking_id' => $bookingB->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 300000,
            'pay' => 300000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(Revenue::class)
            ->assertSet('affiliateRevenue', 500000.0)
            ->assertSet('affiliateBookingCount', 2)
            ->assertSee('SKYALPHA')
            ->assertSee('SKYBETA');
    }

    public function test_legacy_booking_with_null_affiliate_id_is_scoped_by_iphone_affiliate(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $userAffA = User::factory()->create(['affiliate_id' => $affA->id]);
        $userAffA->assignRole('affiliate');

        $paymentMethod = Payment::create(['name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);
        $gallery = Gallery::create(['image' => 'iphone.jpg']);

        $iphoneA = Iphones::create([
            'name' => 'Legacy iPhone Alpha',
            'slug' => 'legacy-iphone-alpha',
            'serial_number' => 'LEGACY001',
            'asset_code' => 'AST-LEG-001',
            'gallery_id' => $gallery->id,
            'user_id' => $userAffA->id,
            'status' => 'ready',
            'affiliate_id' => $affA->id,
        ]);

        // Legacy booking where affiliate_id is NULL
        $booking = Booking::create([
            'iphone_id' => $iphoneA->id,
            'affiliate_id' => null,
            'user_id' => $userAffA->id,
            'customer_name' => 'Legacy Customer',
            'customer_phone' => '0833333333',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 150000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYLEGACY',
            'payment_id' => $paymentMethod->id,
        ]);

        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $paymentMethod->id,
            'amount' => 150000,
            'pay' => 150000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $userAffA->id,
        ]);

        // Affiliate A user sees it
        $this->actingAs($userAffA);
        Livewire::test(Revenue::class)
            ->assertSet('affiliateRevenue', 150000.0)
            ->assertSee('SKYLEGACY')
            ->assertSee('Legacy Customer');

        // Affiliate B user does NOT see it
        $userAffB = User::factory()->create(['affiliate_id' => $affB->id]);
        $userAffB->assignRole('affiliate');
        $this->actingAs($userAffB);

        Livewire::test(Revenue::class)
            ->assertSet('affiliateRevenue', 0.0)
            ->assertDontSee('SKYLEGACY');
    }

    public function test_affiliate_user_cannot_access_other_affiliate_revenue_via_api(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $userAffA = User::factory()->create(['affiliate_id' => $affA->id]);
        $userAffA->assignRole('affiliate-admin');

        $this->actingAs($userAffA);

        // Attempting to access own affiliate revenue: Allowed (200)
        $resOwn = $this->getJson("/api/v1/affiliates/{$affA->id}/revenue");
        $resOwn->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'affiliate_id' => $affA->id,
                ],
            ]);

        // Attempting to access other affiliate revenue: Forbidden (403)
        $resOther = $this->getJson("/api/v1/affiliates/{$affB->id}/revenue");
        $resOther->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_super_admin_can_access_any_affiliate_revenue_via_api(): void
    {
        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Alpha', 'slug' => 'aff-alpha', 'is_active' => true]);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Beta', 'slug' => 'aff-beta', 'is_active' => true]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin);

        $resA = $this->getJson("/api/v1/affiliates/{$affA->id}/revenue");
        $resA->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['affiliate_id' => $affA->id]]);

        $resB = $this->getJson("/api/v1/affiliates/{$affB->id}/revenue");
        $resB->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['affiliate_id' => $affB->id]]);
    }

    public function test_payment_api_prevents_duplicate_double_submit_records(): void
    {
        $aff = Affiliate::create(['code' => 'DUP', 'name' => 'Dup Test', 'slug' => 'dup-test', 'is_active' => true]);
        $user = User::factory()->create(['affiliate_id' => $aff->id]);
        $user->assignRole('affiliate-admin');
        $this->actingAs($user);

        $paymentMethod = Payment::create(['name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);
        $gallery = Gallery::create(['image' => 'iphone.jpg']);
        $iphone = Iphones::create([
            'name' => 'iPhone Test',
            'slug' => 'iphone-test',
            'serial_number' => 'DUP123',
            'asset_code' => 'AST-DUP-001',
            'gallery_id' => $gallery->id,
            'user_id' => $user->id,
            'status' => 'ready',
            'affiliate_id' => $aff->id,
        ]);

        $booking = Booking::create([
            'iphone_id' => $iphone->id,
            'affiliate_id' => $aff->id,
            'user_id' => $user->id,
            'customer_name' => 'Dup Customer',
            'customer_phone' => '0844444444',
            'requested_booking_date' => now()->toDateString(),
            'requested_time' => '10:00:00',
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 200000,
            'created' => now()->toDateString(),
            'booking_code' => 'SKYDUP001',
            'payment_id' => $paymentMethod->id,
        ]);

        // Submit first payment
        $res1 = $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'amount' => 100000,
            'pay' => 100000,
            'type' => 'dp',
            'payment_method' => 'cash',
        ]);
        $res1->assertStatus(201);

        // Submit identical payment immediately (double-click simulation)
        $res2 = $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'amount' => 100000,
            'pay' => 100000,
            'type' => 'dp',
            'payment_method' => 'cash',
        ]);
        $res2->assertStatus(200);

        // Assert only 1 payment was created
        $this->assertEquals(1, BookingPayment::where('booking_id', $booking->id)->count());
    }
}
