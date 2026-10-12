<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Duration;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LateReturnPenaltyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $staffA;
    protected User $staffB;
    protected Iphones $iphone;
    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin']);
        $this->superAdmin->assignRole('super-admin');

        $this->staffA = User::factory()->create(['name' => 'Staff A']);
        $this->staffA->assignRole('staff');

        $this->staffB = User::factory()->create(['name' => 'Staff B']);
        $this->staffB->assignRole('staff');

        $this->payment = Payment::firstOrCreate(
            ['slug' => 'bank_transfer'],
            ['name' => 'Transfer Bank', 'is_active' => true]
        );

        $this->iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-LATE01',
            'asset_code' => 'AST-IP15P-LATE01',
            'status' => 'rented',
        ]);
    }

    private function createFullyPaidLateBooking(int $hoursAgo = 5): Booking
    {
        $now = Carbon::now('Asia/Jakarta');
        $endDate = $now->copy()->subHours($hoursAgo);

        $booking = Booking::create([
            'booking_code' => 'SKY' . strtoupper(uniqid()),
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@example.com',
            'start_booking_date' => $endDate->copy()->subDays(2)->toDateString(),
            'start_time' => $endDate->copy()->subDays(2)->toTimeString(),
            'end_booking_date' => $endDate->toDateString(),
            'end_time' => $endDate->toTimeString(),
            'duration' => 48,
            'price' => 100000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'user_id' => $this->staffA->id,
            'iphone_id' => $this->iphone->id,
            'payment_id' => $this->payment->id,
            'created' => now(),
        ]);

        // Catat pembayaran sewa awal yang sah sebesar Rp 100.000 (LUNAS)
        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $this->payment->id,
            'amount' => 100000,
            'pay' => 100000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $endDate->copy()->subDays(2),
            'user_id' => $this->staffA->id,
            'note' => 'Pelunasan sewa awal 100k',
        ]);

        return $booking;
    }

    /**
     * 1. Booking sudah lunas, tetapi tidak terlambat -> pembayaran denda ditolak
     */
    public function test_booking_already_paid_with_no_late_penalty_rejects_penalty_payment(): void
    {
        Sanctum::actingAs($this->staffA);

        $now = Carbon::now('Asia/Jakarta');
        $futureEnd = $now->copy()->addHours(12);

        $booking = Booking::create([
            'booking_code' => 'SKY' . strtoupper(uniqid()),
            'customer_name' => 'Siti Nur',
            'customer_phone' => '081234567891',
            'customer_email' => 'siti@example.com',
            'start_booking_date' => $now->toDateString(),
            'start_time' => $now->toTimeString(),
            'end_booking_date' => $futureEnd->toDateString(),
            'end_time' => $futureEnd->toTimeString(),
            'duration' => 12,
            'price' => 100000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'user_id' => $this->staffA->id,
            'iphone_id' => $this->iphone->id,
            'payment_id' => $this->payment->id,
            'created' => now(),
        ]);

        BookingPayment::create([
            'booking_id' => $booking->id,
            'payment_id' => $this->payment->id,
            'amount' => 100000,
            'pay' => 100000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => $now,
            'user_id' => $this->staffA->id,
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => 50000,
            'pay' => 50000,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
        $this->assertStringContainsString('tidak memiliki tagihan denda keterlambatan', $response->json('message'));
    }

    /**
     * 2. Booking sudah lunas 100k dan terlambat -> pembayaran denda berhasil diproses terpisah
     * dan pembayaran sewa awal tetap utuh 100k (LUNAS)
     */
    public function test_booking_already_paid_with_late_penalty_successfully_paid_and_recorded_separately(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $expectedPenalty = (float) $booking->late_info['penalty'];
        $this->assertGreaterThan(0, $expectedPenalty);

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => $expectedPenalty,
            'pay' => $expectedPenalty,
            'payment_method' => 'cash',
            'note' => 'Pembayaran Denda Keterlambatan Pengembalian Unit',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'payment_summary' => [
                    'payment_type' => 'penalty',
                    'payment_type_label' => 'Denda Keterlambatan',
                    'payment_status' => 'paid',
                    'is_settled' => true,
                ],
            ]);

        // Verifikasi pembayaran sewa pokok tetap utuh Rp 100.000
        $rentalPayments = $booking->paymentTransactions()->whereIn('type', ['dp', 'payment', 'pelunasan'])->get();
        $this->assertCount(1, $rentalPayments);
        $this->assertEquals(100000, $rentalPayments->first()->amount);

        // Verifikasi denda dicatat sebagai transaksi BookingPayment terpisah bertipe 'penalty'
        $penaltyPayments = $booking->paymentTransactions()->where('type', 'penalty')->get();
        $this->assertCount(1, $penaltyPayments);
        $this->assertEquals($expectedPenalty, $penaltyPayments->first()->amount);

        // Status pembayaran booking tetap paid
        $booking->refresh();
        $this->assertEquals('paid', $booking->payment_status);
    }

    /**
     * 3. Server memvalidasi dan memberlakukan nominal denda sesuai aturan bisnis (tidak percaya nominal arbitrary client)
     */
    public function test_late_penalty_amount_is_validated_and_calculated_by_server(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $expectedPenalty = (float) $booking->late_info['penalty'];

        // Client mencoba mengirim amount = 1000 (jauh di bawah denda server)
        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => 1000,
            'pay' => $expectedPenalty,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);

        // Server harus menegakkan nominal denda terhitung
        $penaltyPayment = $booking->paymentTransactions()->where('type', 'penalty')->first();
        $this->assertNotNull($penaltyPayment);
        $this->assertEquals($expectedPenalty, (float) $penaltyPayment->amount);
    }

    /**
     * 4. Pembayaran denda ditolak jika jumlah uang yang dibayarkan (pay) kurang dari tagihan denda
     */
    public function test_late_penalty_payment_rejected_when_pay_is_insufficient(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $expectedPenalty = (float) $booking->late_info['penalty'];

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => $expectedPenalty,
            'pay' => $expectedPenalty - 5000, // Uang kurang
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('tidak boleh lebih kecil dari tagihan denda', $response->json('message'));

        // Tidak ada transaksi denda yang dibuat
        $this->assertEquals(0, $booking->paymentTransactions()->where('type', 'penalty')->count());
    }

    /**
     * 5. Mencoba mengirim denda yang sama dua kali (idempoten) -> mencegah penagihan ganda
     */
    public function test_attempt_to_submit_same_penalty_twice_prevents_duplicate_charge(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $expectedPenalty = (float) $booking->late_info['penalty'];

        // Permintaan 1: Sukses
        $res1 = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => $expectedPenalty,
            'pay' => $expectedPenalty,
            'payment_method' => 'cash',
        ]);
        $res1->assertStatus(201);

        // Permintaan 2: Mengirim denda yang sama lagi (misal double tap / retry timeout)
        $res2 = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => $expectedPenalty,
            'pay' => $expectedPenalty,
            'payment_method' => 'cash',
        ]);
        $res2->assertStatus(200);
        $this->assertStringContainsString('sudah pernah dicatat', $res2->json('message'));

        // Jumlah transaksi denda tetap tepat 1 (TIDAK ADA DUPLIKAT)
        $this->assertEquals(1, $booking->paymentTransactions()->where('type', 'penalty')->count());
    }

    /**
     * 6. Alur lengkap pengembalian: Bayar denda via /payments kemudian completeReturn tidak menduplikasi transaksi denda
     */
    public function test_complete_return_after_penalty_payment_does_not_duplicate_penalty_transaction(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $expectedPenalty = (float) $booking->late_info['penalty'];

        // Langkah 1: Bayar denda di kasir
        $payRes = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => $expectedPenalty,
            'pay' => $expectedPenalty,
            'payment_method' => 'cash',
        ]);
        $payRes->assertStatus(201);

        // Langkah 2: Selesaikan pengembalian unit
        $returnRes = $this->postJson("/api/v1/returns/complete/{$booking->booking_code}", [
            'late_fee' => $expectedPenalty,
            'condition' => 'Baik / Sesuai Kesepakatan',
            'battery_health' => 95,
        ]);
        $returnRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'booking_status' => 'returned',
                    'unit_status' => 'ready',
                ],
            ]);

        // Verifikasi transaksi denda tetap TEPAT 1 (TIDAK ADA DUPLIKASI dari completeReturn)
        $this->assertEquals(1, $booking->paymentTransactions()->where('type', 'penalty')->count());

        // Verifikasi status unit iPhone kembali ke ready
        $this->iphone->refresh();
        $this->assertEquals('ready', $this->iphone->status);
    }

    /**
     * 7. Ditolak jika booking berstatus cancelled atau pending
     */
    public function test_penalty_payment_rejected_for_cancelled_or_pending_booking(): void
    {
        Sanctum::actingAs($this->staffA);

        $cancelled = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $cancelled->update(['status' => 'cancelled']);

        $res1 = $this->postJson("/api/v1/bookings/{$cancelled->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => 50000,
            'pay' => 50000,
        ]);
        $res1->assertStatus(422);

        $pending = $this->createFullyPaidLateBooking(hoursAgo: 6);
        $pending->update(['status' => 'pending']);

        $res2 = $this->postJson("/api/v1/bookings/{$pending->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => 50000,
            'pay' => 50000,
        ]);
        $res2->assertStatus(422);
    }

    /**
     * 8. Pengguna tanpa izin tidak dapat memproses pembayaran booking staf lain (403 Forbidden)
     */
    public function test_unauthorized_user_cannot_process_penalty_payment(): void
    {
        Sanctum::actingAs($this->staffB);

        $booking = $this->createFullyPaidLateBooking(hoursAgo: 6); // Milik Staff A

        $response = $this->postJson("/api/v1/bookings/{$booking->booking_code}/payments", [
            'type' => 'penalty',
            'amount' => 50000,
            'pay' => 50000,
        ]);

        $response->assertStatus(403);
    }
}
