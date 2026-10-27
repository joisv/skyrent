<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CashDrawerShift;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierShiftApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->affiliate = Affiliate::firstOrCreate(['id' => 1], [
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
    }

    public function test_can_open_shift_and_retrieve_current_shift(): void
    {
        // 1. Initially no active shift
        $resCurrent = $this->getJson('/api/v1/cashier/shift/current');
        $resCurrent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_shift' => false,
                    'shift' => null,
                ],
            ]);

        // 2. Open shift with opening cash float Rp 200.000
        $resOpen = $this->postJson('/api/v1/cashier/shift/open', [
            'opening_cash_float' => 200000,
            'notes' => 'Shift pagi kasir 1',
            'branch_id' => $this->affiliate->id,
        ]);

        $resOpen->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'branch_id' => $this->affiliate->id,
                    'opening_cash_float' => 200000,
                    'expected_cash_now' => 200000,
                    'status' => 'open',
                ],
            ]);

        // 3. Concurrency guard: opening again on same branch fails with 422
        $resOpenDup = $this->postJson('/api/v1/cashier/shift/open', [
            'opening_cash_float' => 100000,
            'branch_id' => $this->affiliate->id,
        ]);
        $resOpenDup->assertStatus(422);

        // 4. Current shift reflects active shift
        $resCurrent2 = $this->getJson('/api/v1/cashier/shift/current');
        $resCurrent2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_shift' => true,
                    'shift' => [
                        'branch_id' => $this->affiliate->id,
                        'opening_cash_float' => 200000,
                        'status' => 'open',
                    ],
                ],
            ]);
    }

    public function test_can_close_shift_and_enforce_discrepancy_reason(): void
    {
        // Open shift
        $resOpen = $this->postJson('/api/v1/cashier/shift/open', [
            'opening_cash_float' => 100000,
            'branch_id' => $this->affiliate->id,
        ]);
        $resOpen->assertStatus(201);
        $shiftId = $resOpen->json('data.id');

        // Discrepancy > 5000 without reason fails with 422
        $resCloseFail = $this->postJson('/api/v1/cashier/shift/close', [
            'actual_cash_counted' => 80000, // Shortage Rp 20.000
            'shift_id' => $shiftId,
        ]);
        $resCloseFail->assertStatus(422)
            ->assertJsonValidationErrors(['difference_reason']);

        // Discrepancy > 5000 with valid reason succeeds
        $resCloseOk = $this->postJson('/api/v1/cashier/shift/close', [
            'actual_cash_counted' => 80000,
            'difference_reason' => 'Uang receh kembalian kurang Rp 20.000',
            'closing_notes' => 'Selesai shift sore',
            'shift_id' => $shiftId,
        ]);

        $resCloseOk->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $shiftId,
                    'actual_cash_counted' => 80000,
                    'cash_difference' => -20000,
                    'status' => 'closed',
                ],
            ]);

        // Current shift is now empty
        $resCurrent = $this->getJson('/api/v1/cashier/shift/current');
        $resCurrent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_shift' => false,
                ],
            ]);
    }

    public function test_shift_closing_with_cash_inflow_and_exact_denominations(): void
    {
        // 1. Open shift with Rp 200.000 opening float
        $resOpen = $this->postJson('/api/v1/cashier/shift/open', [
            'opening_cash_float' => 200000,
            'notes' => 'Shift modal Rp 200.000',
            'branch_id' => $this->affiliate->id,
        ]);
        $resOpen->assertStatus(201);
        $shiftId = $resOpen->json('data.id');

        // 2. Add cash payment method
        $cashPayment = Payment::create(['name' => 'Tunai / Cash', 'slug' => 'cash', 'is_active' => true]);

        // 3. Create dummy booking and cash booking payment of Rp 90.000
        $booking = Booking::factory()->create(['affiliate_id' => $this->affiliate->id]);
        BookingPayment::create([
            'booking_id' => $booking->id,
            'affiliate_id' => $this->affiliate->id,
            'payment_id' => $cashPayment->id,
            'user_id' => $this->user->id,
            'amount' => 90000,
            'type' => 'payment',
            'is_void' => false,
            'paid_at' => Carbon::now('Asia/Jakarta'),
        ]);

        // 4. Current shift expects Rp 290.000 (Float 200k + Inflow 90k)
        $resCurrent = $this->getJson('/api/v1/cashier/shift/current');
        $resCurrent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_shift' => true,
                    'shift' => [
                        'opening_cash_float' => 200000,
                        'current_cash_inflow' => 90000,
                        'expected_cash_now' => 290000,
                    ],
                ],
            ]);

        // 5. Close shift with Rp 290.000 actual cash (100k*2 + 50k*1 + 20k*2)
        $resClose = $this->postJson('/api/v1/cashier/shift/close', [
            'actual_cash_counted' => 290000,
            'shift_id' => $shiftId,
        ]);

        $resClose->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $shiftId,
                    'expected_cash_total' => 290000,
                    'actual_cash_counted' => 290000,
                    'cash_difference' => 0,
                    'status' => 'closed',
                ],
            ]);
    }

    public function test_shift_history_timestamps_and_unclosed_active_shift(): void
    {
        // 1. Create a closed shift
        $closedShift = CashDrawerShift::create([
            'branch_id' => $this->affiliate->id,
            'shift_number' => 'SHF-PST-261027-0001',
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
            'opened_at' => Carbon::parse('2026-10-27 08:00:00', 'Asia/Jakarta'),
            'closed_at' => Carbon::parse('2026-10-27 16:00:00', 'Asia/Jakarta'),
            'opening_cash_float' => 200000,
            'expected_cash_total' => 290000,
            'actual_cash_counted' => 290000,
            'cash_difference' => 0,
            'status' => 'closed',
        ]);

        // 2. Create an open active shift
        $openShift = CashDrawerShift::create([
            'branch_id' => $this->affiliate->id,
            'shift_number' => 'SHF-PST-261027-0002',
            'opened_by' => $this->user->id,
            'opened_at' => Carbon::parse('2026-10-27 18:15:51', 'Asia/Jakarta'),
            'closed_at' => null,
            'opening_cash_float' => 200000,
            'status' => 'open',
        ]);

        // 3. Query history
        $resHistory = $this->getJson('/api/v1/cashier/shift/history');
        $resHistory->assertStatus(200);

        $items = $resHistory->json('data');
        $this->assertCount(2, $items);

        // Open shift is first (latest opened_at)
        $this->assertEquals($openShift->id, $items[0]['id']);
        $this->assertEquals('open', $items[0]['status']);
        $this->assertNotNull($items[0]['opened_at']);
        $this->assertNull($items[0]['closed_at']); // NOT fabricated!

        // Closed shift is second
        $this->assertEquals($closedShift->id, $items[1]['id']);
        $this->assertEquals('closed', $items[1]['status']);
        $this->assertNotNull($items[1]['opened_at']);
        $this->assertNotNull($items[1]['closed_at']);
        $this->assertStringContainsString('2026-10-27T16:00:00', $items[1]['closed_at']);
    }
}
