<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Duration;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\ReturnIphone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnCompleteApiTest extends TestCase
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
            ['slug' => 'cash'],
            ['name' => 'Tunai (Kasir)', 'is_active' => true]
        );

        $this->iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'serial_number' => 'SN-IP15P-RET01',
            'asset_code' => 'AST-IP15P-RET01',
            'status' => 'rented',
        ]);
    }

    /**
     * Scenario 1: A confirmed booking that is not late -> successfully returned, unit restored to ready.
     */
    public function test_confirmed_booking_not_late_is_marked_as_returned_and_unit_ready(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = Booking::create([
            'booking_code' => 'SKY260909ONTIME',
            'iphone_id' => $this->iphone->id,
            'user_id' => $this->staffA->id,
            'payment_id' => $this->payment->id,
            'customer_name' => 'Budi Pratama',
            'customer_phone' => '081234567890',
            'start_booking_date' => Carbon::today()->subDay()->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => Carbon::today()->addDay()->toDateString(),
            'end_time' => '22:00',
            'duration' => 24,
            'price' => 350000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/returns/complete/{$booking->booking_code}", [
            'condition' => 'Mulus (Sempurna)',
            'battery_health_return' => 95,
            'late_fee' => 0,
            'damage_fee' => 0,
            'deposit_refunded' => 0,
            'refund_method' => 'Tanpa Denda',
            'accessories_returned' => ['kabel', 'box'],
            'staff_notes' => 'Pengembalian tepat waktu diselesaikan langsung.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'booking_code' => 'SKY260909ONTIME',
                    'booking_status' => 'returned',
                    'unit_status' => 'ready',
                    'late_fee' => 0,
                ],
            ]);

        // Verify Database
        $booking->refresh();
        $this->assertEquals('returned', $booking->status);

        $this->iphone->refresh();
        $this->assertEquals('ready', $this->iphone->status);

        $returnRecord = ReturnIphone::where('booking_id', $booking->id)->first();
        $this->assertNotNull($returnRecord);
        $this->assertEquals('Mulus (Sempurna)', $returnRecord->condition);
        $this->assertEquals(0, (float) $returnRecord->penalty_fee);
    }

    /**
     * Scenario 2: A confirmed booking that is late and requires penalty processing.
     */
    public function test_confirmed_booking_late_calculates_penalty_and_creates_penalty_payment(): void
    {
        Sanctum::actingAs($this->staffA);

        $booking = Booking::create([
            'booking_code' => 'SKY260909LATE01',
            'iphone_id' => $this->iphone->id,
            'user_id' => $this->staffA->id,
            'payment_id' => $this->payment->id,
            'customer_name' => 'Citra Dewi',
            'customer_phone' => '081234567891',
            'start_booking_date' => Carbon::today()->subDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_booking_date' => Carbon::today()->subDays(1)->toDateString(),
            'end_time' => '10:00',
            'duration' => 48,
            'price' => 600000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $response = $this->postJson("/api/v1/returns/complete/{$booking->booking_code}", [
            'condition' => 'Dent / Baret Dalam',
            'battery_health_return' => 93,
            'late_fee' => 100000,
            'damage_fee' => 50000,
            'deposit_refunded' => 0,
            'refund_method' => 'Tunai (Kasir)',
            'accessories_returned' => ['kabel'],
            'staff_notes' => 'Terlambat 24 jam dan ada dent.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'booking_code' => 'SKY260909LATE01',
                    'booking_status' => 'returned',
                    'unit_status' => 'ready',
                    'late_fee' => 100000,
                    'damage_fee' => 50000,
                    'total_deduction' => 150000,
                ],
            ]);

        // Verify Database
        $booking->refresh();
        $this->assertEquals('returned', $booking->status);

        $this->iphone->refresh();
        $this->assertEquals('ready', $this->iphone->status);

        $returnRecord = ReturnIphone::where('booking_id', $booking->id)->first();
        $this->assertNotNull($returnRecord);
        $this->assertEquals(150000, (float) $returnRecord->penalty_fee);

        $penaltyPayment = BookingPayment::where('booking_id', $booking->id)
            ->where('type', 'penalty')
            ->first();
        $this->assertNotNull($penaltyPayment);
        $this->assertEquals(150000, (float) $penaltyPayment->amount);
    }

    /**
     * Scenario 3: A booking whose status is no longer confirmed/rented (already returned or cancelled).
     */
    public function test_booking_not_in_confirmed_or_rented_status_is_rejected(): void
    {
        Sanctum::actingAs($this->staffA);

        // Case A: Already returned
        $returnedBooking = Booking::create([
            'booking_code' => 'SKY260909ALRDY',
            'iphone_id' => $this->iphone->id,
            'user_id' => $this->staffA->id,
            'customer_name' => 'Eko Susanto',
            'customer_phone' => '081234567899',
            'duration' => 24,
            'price' => 300000,
            'status' => 'returned',
            'created' => now(),
        ]);

        $responseA = $this->postJson("/api/v1/returns/complete/{$returnedBooking->booking_code}");
        $responseA->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'ALREADY_RETURNED',
            ]);

        // Case B: Cancelled
        $cancelledBooking = Booking::create([
            'booking_code' => 'SKY260909CNCL',
            'iphone_id' => $this->iphone->id,
            'user_id' => $this->staffA->id,
            'customer_name' => 'Fajar Nugroho',
            'customer_phone' => '081234567898',
            'duration' => 24,
            'price' => 300000,
            'status' => 'cancelled',
            'created' => now(),
        ]);

        $responseB = $this->postJson("/api/v1/returns/complete/{$cancelledBooking->booking_code}");
        $responseB->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'BOOKING_CANCELLED',
            ]);
    }

    /**
     * Scenario 7: A user attempting to return a booking they are not authorized to access.
     */
    public function test_unauthorized_user_cannot_return_other_staff_booking(): void
    {
        // Booking belongs to Staff A
        $booking = Booking::create([
            'booking_code' => 'SKY260909STAFFA',
            'iphone_id' => $this->iphone->id,
            'user_id' => $this->staffA->id,
            'customer_name' => 'Gilang Ramadhan',
            'customer_phone' => '081234567897',
            'duration' => 24,
            'price' => 300000,
            'status' => 'confirmed',
            'created' => now(),
        ]);

        // Staff B attempts to complete return
        Sanctum::actingAs($this->staffB);

        $response = $this->postJson("/api/v1/returns/complete/{$booking->booking_code}", [
            'condition' => 'Mulus (Sempurna)',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
            ]);

        // Status must remain unchanged
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
    }
}
