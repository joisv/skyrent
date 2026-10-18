<?php

namespace Tests\Feature;

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
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialAttributionAndVoidTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Affiliate $pusat;
    protected Affiliate $purwoharjo;
    protected Payment $cashPayment;
    protected Iphones $iphone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShopSettingsSeeder::class);

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'affiliate-admin']);
        Role::firstOrCreate(['name' => 'affiliate']);

        $this->pusat = Affiliate::create([
            'id' => 4,
            'code' => 'PST',
            'name' => 'Genteng Pusat',
            'slug' => 'genteng-pusat',
            'is_active' => true,
        ]);

        $this->purwoharjo = Affiliate::create([
            'id' => 3,
            'code' => 'PWJ',
            'name' => 'Affiliate Purwoharjo',
            'slug' => 'affiliate-purwoharjo',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Pusat',
            'email' => 'admin@skyrent.id',
            'affiliate_id' => $this->pusat->id,
        ]);
        $this->admin->assignRole('admin');

        $this->cashPayment = Payment::create([
            'id' => 1,
            'name' => 'Cash',
            'slug' => 'cash',
            'is_active' => true,
        ]);

        $gallery = Gallery::create(['image' => 'iphone.jpg']);
        $this->iphone = Iphones::create([
            'name' => 'iPhone 13',
            'slug' => 'iphone-13',
            'serial_number' => 'SN13TEST001',
            'asset_code' => 'AST-IP13-001',
            'gallery_id' => $gallery->id,
            'user_id' => $this->admin->id,
            'status' => 'ready',
            'affiliate_id' => $this->pusat->id,
        ]);
    }

    public function test_booking_payment_automatically_inherits_affiliate_id_from_booking(): void
    {
        $booking = Booking::create([
            'booking_code' => 'TEST001',
            'customer_name' => 'Customer A',
            'customer_phone' => '08123456789',
            'customer_address' => 'Genteng',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->purwoharjo->id,
            'user_id' => $this->admin->id,
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 100000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 100000,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
        ]);

        $this->assertEquals($this->purwoharjo->id, $payment->affiliate_id);
    }

    public function test_booking_total_paid_excludes_voided_payments(): void
    {
        $booking = Booking::create([
            'booking_code' => 'TEST002',
            'customer_name' => 'Customer B',
            'customer_phone' => '08123456780',
            'customer_address' => 'Genteng',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->pusat->id,
            'user_id' => $this->admin->id,
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 100000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        // Primary payment
        $payment1 = BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 100000,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'is_void' => false,
        ]);

        // Duplicate phantom payment marked void
        $payment2 = BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 100000,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'is_void' => true,
            'void_reason' => 'rapid_duplicate_burst',
        ]);

        $booking->updatePaymentStatus();
        $booking->refresh();

        $this->assertEquals(100000.0, $booking->total_paid);
        $this->assertEquals(0.0, $booking->remaining_payment);
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals(1, BookingPayment::active()->where('booking_id', $booking->id)->count());
    }

    public function test_backfill_command_assigns_affiliate_from_booking_and_cashier(): void
    {
        // Case 1: Booking has affiliate_id
        $bookingWithAff = Booking::create([
            'booking_code' => 'TEST003',
            'customer_name' => 'Customer C',
            'customer_phone' => '08123456781',
            'customer_address' => 'Genteng',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->purwoharjo->id,
            'user_id' => $this->admin->id,
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 50000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Force NULL affiliate_id by using DB query directly
        $id1 = BookingPayment::insertGetId([
            'booking_id' => $bookingWithAff->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 50000,
            'pay' => 50000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
            'affiliate_id' => null,
            'is_void' => false,
        ]);

        // Case 2: Historical booking with NULL affiliate_id (Cashier at Genteng Pusat)
        $bookingNullAff = Booking::create([
            'booking_code' => 'TEST004',
            'customer_name' => 'Customer D',
            'customer_phone' => '08123456782',
            'customer_address' => 'Genteng',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => null,
            'user_id' => $this->admin->id,
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 60000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $id2 = BookingPayment::insertGetId([
            'booking_id' => $bookingNullAff->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 60000,
            'pay' => 60000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
            'affiliate_id' => null,
            'is_void' => false,
        ]);

        $this->artisan('finance:backfill-payment-attribution --apply')
            ->assertExitCode(0);

        $this->assertEquals($this->purwoharjo->id, BookingPayment::find($id1)->affiliate_id);
        $this->assertEquals($this->pusat->id, BookingPayment::find($id2)->affiliate_id);
    }

    public function test_void_duplicate_payments_command_audit_and_apply(): void
    {
        $booking = Booking::create([
            'id' => 3211,
            'booking_code' => 'SKY260808HZAV',
            'customer_name' => 'Duplicate Customer',
            'customer_phone' => '08123456789',
            'customer_address' => 'Genteng',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->pusat->id,
            'user_id' => $this->admin->id,
            'start_booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => now()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 40000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Insert primary ID 392 and duplicate ID 393 using forceCreate
        $primary = BookingPayment::forceCreate([
            'id' => 392,
            'booking_id' => $booking->id,
            'affiliate_id' => $this->pusat->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 40000,
            'pay' => 40000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'is_void' => false,
        ]);

        $duplicate = BookingPayment::forceCreate([
            'id' => 393,
            'booking_id' => $booking->id,
            'affiliate_id' => $this->pusat->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 40000,
            'pay' => 40000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'is_void' => false,
        ]);

        // Dry run should not modify is_void
        $this->artisan('finance:void-duplicate-payments --dry-run')
            ->assertExitCode(0);
        $this->assertFalse($duplicate->fresh()->is_void);

        // Apply should soft-void duplicate
        $this->artisan('finance:void-duplicate-payments --apply')
            ->assertExitCode(0);

        $dupFresh = $duplicate->fresh();
        $this->assertTrue($dupFresh->is_void);
        $this->assertEquals('rapid_duplicate_burst', $dupFresh->void_reason);
        $this->assertNotNull($dupFresh->voided_at);

        // Sibling primary should remain active
        $this->assertFalse($primary->fresh()->is_void);

        // Booking status adjusted correctly
        $booking->refresh();
        $this->assertEquals(40000.0, $booking->total_paid);
        $this->assertEquals('paid', $booking->payment_status);
    }
}
