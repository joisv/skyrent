<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Expense;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Affiliate $affiliate;

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
    }

    public function test_can_create_expense(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'amount' => 50000,
            'category' => 'operasional',
            'notes' => 'Beli kertas kasir thermal dan pulpen',
            'spent_at' => now()->toIso8601String(),
            'affiliate_id' => $this->affiliate->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'amount' => 50000,
                    'category' => 'operasional',
                    'notes' => 'Beli kertas kasir thermal dan pulpen',
                ],
            ]);

        $this->assertDatabaseHas('expenses', [
            'amount' => 50000,
            'category' => 'operasional',
            'affiliate_id' => $this->affiliate->id,
        ]);
    }

    public function test_can_list_expenses_and_view_summary(): void
    {
        Expense::create([
            'affiliate_id' => $this->affiliate->id,
            'user_id' => $this->user->id,
            'amount' => 30000,
            'category' => 'kebersihan',
            'notes' => 'Sapu dan pel',
            'spent_at' => now(),
        ]);

        Expense::create([
            'affiliate_id' => $this->affiliate->id,
            'user_id' => $this->user->id,
            'amount' => 20000,
            'category' => 'operasional',
            'notes' => 'Kopi kasir',
            'spent_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/expenses?period=hari ini');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'total_expenses' => 50000,
                    'count' => 2,
                ],
            ]);
    }

    public function test_can_delete_expense(): void
    {
        $expense = Expense::create([
            'affiliate_id' => $this->affiliate->id,
            'user_id' => $this->user->id,
            'amount' => 25000,
            'category' => 'lain_lain',
            'notes' => 'Test salah input',
            'spent_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/expenses/{$expense->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_sales_report_includes_expenses_and_calculates_net_cash(): void
    {
        $cashPayment = Payment::create(['name' => 'Tunai Kasir', 'slug' => 'cash']);

        $phone = Iphones::factory()->create();

        $booking = Booking::create([
            'booking_code' => 'SKY-EXP-TEST',
            'iphone_id' => $phone->id,
            'customer_name' => 'Testing Expense',
            'customer_phone' => '08123456789',
            'affiliate_id' => $this->affiliate->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDay()->toDateString(),
            'duration' => 24,
            'price' => 200000,
            'created' => now(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Payment of Rp 200.000 cash
        BookingPayment::create([
            'booking_id' => $booking->id,
            'affiliate_id' => $this->affiliate->id,
            'payment_id' => $cashPayment->id,
            'amount' => 200000,
            'pay' => 200000,
            'change' => 0,
            'type' => 'payment',
            'paid_at' => now(),
            'user_id' => $this->user->id,
            'is_void' => false,
        ]);

        // Branch Expense of Rp 50.000
        Expense::create([
            'affiliate_id' => $this->affiliate->id,
            'user_id' => $this->user->id,
            'amount' => 50000,
            'category' => 'operasional',
            'notes' => 'Listrik token',
            'spent_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/sales?period=hari ini');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'summary' => [
                    'totalRevenue' => 200000,
                    'cashAmount' => 200000,
                    'totalExpenses' => 50000,
                    'grossCash' => 200000,
                    'netCash' => 150000, // 200.000 - 50.000
                ],
            ]);
    }
}
