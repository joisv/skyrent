<?php

namespace Tests\Feature\Api;

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

class RentalExtensionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Iphones $iphone;
    protected Payment $cashPayment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['name' => 'Admin Outlet']);
        $this->admin->assignRole('admin');

        $this->cashPayment = Payment::firstOrCreate(
            ['slug' => 'cash'],
            ['name' => 'Tunai (Cash)', 'is_active' => true]
        );

        $this->iphone = Iphones::factory()->create([
            'name' => 'iPhone 11',
            'serial_number' => 'DNPP9811M991',
            'status' => 'ready',
        ]);

        Sanctum::actingAs($this->admin);
    }

    /**
     * Test A — Fully paid original rental followed by an extension
     *
     * - Original rental charge: Rp70,000.
     * - Verified original rental payment: Rp70,000.
     * - Extension charge: Rp50,000 for 6 additional hours.
     * - Verified successful extension payment: Rp50,000.
     */
    public function test_a_fully_paid_original_rental_followed_by_extension(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTA01',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 70000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'payment_id' => $this->cashPayment->id,
            'user_id' => $this->admin->id,
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-INIT-70K',
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 70000,
            'pay' => 70000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'note' => 'Pembayaran awal sewa 24 jam',
        ]);

        // 1. Process extension payment via /payments endpoint
        $paymentResponse = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 50000,
            'pay' => 50000,
            'payment_method' => 'cash',
            'type' => 'extend',
            'note' => 'Pembayaran Tambah Jam 6 Jam via Tunai',
        ]);

        $paymentResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'payment_summary' => [
                    'booking_code' => $booking->booking_code,
                    'price' => 50000,
                    'total_amount' => 50000,
                    'amount_paid' => 50000,
                    'remaining_payment' => 0,
                    'payment_type' => 'extend',
                    'is_settled' => true,
                ],
            ]);

        // 2. Extend booking duration via /extend endpoint
        $extendResponse = $this->postJson("/api/v1/bookings/{$booking->booking_code}/extend", [
            'hours' => 6,
            'price' => 50000,
            'payment_method' => 'cash',
            'pay' => 50000,
        ]);

        $extendResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'hours_added' => 6,
                'new_duration' => 30,
            ]);

        $freshBooking = $booking->fresh(['paymentTransactions']);

        // Assertions:
        // - Original rental price remains 70,000 (not mutated to 120,000)
        $this->assertEquals(70000.0, (float) $freshBooking->price);

        // - Booking is not incorrectly reset to partial/dp
        $this->assertEquals('paid', $freshBooking->payment_status);

        // - Outstanding balance is 0 (not 120,000)
        $this->assertEquals(0.0, (float) $freshBooking->remaining_payment);

        // - Initial payment and extend payment are recorded correctly
        $extendTransactions = $freshBooking->paymentTransactions->where('type', 'extend');
        $this->assertCount(1, $extendTransactions);
        $this->assertEquals(50000.0, (float) $extendTransactions->first()->amount);

        // - Receipt verification: primary transaction amount is 50,000, not 70,000
        $receiptResponse = $this->getJson("/api/v1/receipts/{$booking->booking_code}?type=extend");
        $receiptResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'type' => 'extend',
                    'type_label' => 'TAMBAH JAM (EXTEND)',
                    'booking_code' => $booking->booking_code,
                    'unit_name' => 'iPhone 11',
                    'total_amount' => 50000,
                    'paid_amount' => 50000,
                    'remaining_amount' => 0,
                ],
            ]);

        $escPos = $receiptResponse->json('data.esc_pos_text');
        $this->assertStringContainsString('STRUK PERPANJANGAN SEWA', $escPos);
        $this->assertStringContainsString('TAMBAH JAM (EXTEND)', $escPos);
        $this->assertStringContainsString('50.000', $escPos);
    }

    /**
     * Test B — Original rental is partially paid
     *
     * Verify that the extension workflow does not incorrectly mark the original rental
     * as fully paid when a genuine original rental balance remains outstanding.
     */
    public function test_b_original_rental_partially_paid_preserves_partial_status(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTB02',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Siti Rahma',
            'customer_phone' => '081234567891',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 70000,
            'status' => 'rented',
            'payment_status' => 'partial',
            'payment_id' => $this->cashPayment->id,
            'user_id' => $this->admin->id,
            'created' => now(),
        ]);

        // Customer only paid DP Rp30,000 of Rp70,000
        BookingPayment::create([
            'payment_code' => 'PAY-DP-30K',
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 30000,
            'pay' => 30000,
            'change' => 0,
            'type' => 'dp',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'note' => 'DP sewa 24 jam',
        ]);

        $this->assertEquals('partial', $booking->payment_status);
        $this->assertEquals(40000.0, (float) $booking->remaining_payment);

        // Process extension payment Rp50,000
        $paymentResponse = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 50000,
            'pay' => 50000,
            'payment_method' => 'cash',
            'type' => 'extend',
            'note' => 'Pembayaran Tambah Jam 6 Jam via Tunai',
        ]);

        $paymentResponse->assertStatus(201);

        $extendResponse = $this->postJson("/api/v1/bookings/{$booking->booking_code}/extend", [
            'hours' => 6,
            'price' => 50000,
            'payment_method' => 'cash',
            'pay' => 50000,
        ]);

        $extendResponse->assertStatus(200);

        $freshBooking = $booking->fresh();

        // Must still be partial because original rental still has Rp40,000 unpaid!
        $this->assertEquals('partial', $freshBooking->payment_status);
        $this->assertEquals(40000.0, (float) $freshBooking->remaining_payment);
        $this->assertEquals(70000.0, (float) $freshBooking->price);
    }

    /**
     * Test C — Extension payment fails or is cancelled
     *
     * Verify that failed or invalid extension requests do not alter booking status.
     */
    public function test_c_extension_fails_on_invalid_parameters(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTC03',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Rian Pratama',
            'customer_phone' => '081234567892',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 70000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'payment_id' => $this->cashPayment->id,
            'user_id' => $this->admin->id,
            'created' => now(),
        ]);

        // Attempt extend with 0 hours
        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/extend", [
            'hours' => 0,
            'price' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);

        $fresh = $booking->fresh();
        $this->assertEquals(24, (int) $fresh->duration);
        $this->assertEquals('paid', $fresh->payment_status);
        $this->assertEquals(70000.0, (float) $fresh->price);
    }

    /**
     * Test D — Multiple extensions
     *
     * Verify multiple extension charges are not overwritten, omitted, or double-counted.
     */
    public function test_d_multiple_extensions_accumulate_correctly(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTD04',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '081234567893',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 70000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'payment_id' => $this->cashPayment->id,
            'user_id' => $this->admin->id,
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-INIT-70KD',
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 70000,
            'pay' => 70000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'note' => 'Pembayaran awal sewa',
        ]);

        // Extension 1: 6 hours, Rp50,000
        $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 50000,
            'pay' => 50000,
            'payment_method' => 'cash',
            'type' => 'extend',
            'note' => 'Tambah Jam 1 (6 Jam)',
        ])->assertStatus(201);

        $this->postJson("/api/v1/bookings/{$booking->booking_code}/extend", [
            'hours' => 6,
            'price' => 50000,
            'payment_method' => 'cash',
        ])->assertStatus(200);

        // Extension 2: 12 hours, Rp75,000
        $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 75000,
            'pay' => 100000,
            'payment_method' => 'cash',
            'type' => 'extend',
            'note' => 'Tambah Jam 2 (12 Jam)',
        ])->assertStatus(201);

        $this->postJson("/api/v1/bookings/{$booking->booking_code}/extend", [
            'hours' => 12,
            'price' => 75000,
            'payment_method' => 'cash',
        ])->assertStatus(200);

        $fresh = $booking->fresh(['paymentTransactions']);

        // Original price must still be 70,000
        $this->assertEquals(70000.0, (float) $fresh->price);
        // Duration must be 24 + 6 + 12 = 42 hours
        $this->assertEquals(42, (int) $fresh->duration);
        // Payment status must still be paid
        $this->assertEquals('paid', $fresh->payment_status);
        // Remaining payment must be 0
        $this->assertEquals(0.0, (float) $fresh->remaining_payment);

        // 2 extend transactions
        $extendTx = $fresh->paymentTransactions->where('type', 'extend');
        $this->assertCount(2, $extendTx);
        $this->assertEquals(125000.0, (float) $extendTx->sum('amount'));
    }

    /**
     * Test E — Existing payment types retain behavior
     */
    public function test_e_existing_payment_types_retain_behavior(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTE05',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Fajar Nugraha',
            'customer_phone' => '081234567894',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 100000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        // DP payment of 50,000
        $dpRes = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 50000,
            'pay' => 50000,
            'payment_method' => 'cash',
            'type' => 'dp',
        ]);
        $dpRes->assertStatus(201);
        $this->assertEquals('partial', $booking->fresh()->payment_status);
        $this->assertEquals(50000.0, (float) $booking->fresh()->remaining_payment);

        // Full settlement of remaining 50,000 with cash 100,000 (change 50,000)
        $payRes = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'amount' => 50000,
            'pay' => 100000,
            'payment_method' => 'cash',
            'type' => 'payment',
        ]);
        $payRes->assertStatus(201)
            ->assertJson([
                'payment_summary' => [
                    'amount' => 50000,
                    'cash_received' => 100000,
                    'change' => 50000,
                    'is_settled' => true,
                ],
            ]);

        $this->assertEquals('paid', $booking->fresh()->payment_status);
        $this->assertEquals(0.0, (float) $booking->fresh()->remaining_payment);
    }

    /**
     * Test F — Receipt regression
     *
     * Initial rental receipts still show initial rental details,
     * while extension receipts display extension details.
     */
    public function test_f_receipt_regression_initial_vs_extension(): void
    {
        $today = Carbon::today('Asia/Jakarta');

        $booking = Booking::create([
            'booking_code' => 'SKY11TESTF06',
            'iphone_id' => $this->iphone->id,
            'customer_name' => 'Maya Indah',
            'customer_phone' => '081234567895',
            'pickup_type' => 'Outlet',
            'start_booking_date' => $today->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => $today->copy()->addDay()->toDateString(),
            'end_time' => '10:00',
            'duration' => 24,
            'price' => 70000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-INIT-70KF',
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 70000,
            'pay' => 70000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'note' => 'Pembayaran awal sewa',
        ]);

        // Initial rental receipt
        $initialReceipt = $this->getJson("/api/v1/receipts/{$booking->booking_code}?type=paymentSettlement");
        $initialReceipt->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'paymentSettlement',
                    'type_label' => 'PELUNASAN SEWA',
                    'total_amount' => 70000,
                    'paid_amount' => 70000,
                ],
            ]);

        // Add extension payment
        BookingPayment::create([
            'payment_code' => 'PAY-EXT-50KF',
            'booking_id' => $booking->id,
            'payment_id' => $this->cashPayment->id,
            'amount' => 50000,
            'pay' => 50000,
            'change' => 0,
            'type' => 'extend',
            'paid_at' => now(),
            'user_id' => $this->admin->id,
            'note' => 'Penambahan durasi sewa 6 jam',
        ]);

        // Extension receipt
        $extReceipt = $this->getJson("/api/v1/receipts/{$booking->booking_code}?type=extend");
        $extReceipt->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'extend',
                    'type_label' => 'TAMBAH JAM (EXTEND)',
                    'total_amount' => 50000,
                    'paid_amount' => 50000,
                    'remaining_amount' => 0,
                ],
            ]);

        $this->assertStringContainsString('STRUK PERPANJANGAN SEWA', $extReceipt->json('data.esc_pos_text'));
        $this->assertStringContainsString('50.000', $extReceipt->json('data.esc_pos_text'));
    }
}
