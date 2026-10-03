<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_completed_transactions_for_receipt_history(): void
    {
        $iphone1 = Iphones::factory()->create(['name' => 'iPhone 15 Pro 128GB']);
        $iphone2 = Iphones::factory()->create(['name' => 'iPhone 14 Pro 256GB']);
        $cashPayment = Payment::create(['name' => 'Tunai (Cash)', 'slug' => 'cash', 'is_active' => true]);

        // Booking 1: Rented
        $b1 = Booking::create([
            'booking_code' => 'SKY260909HST01',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Ahmad Fauzi',
            'customer_phone' => '081234567890',
            'duration' => 24,
            'price' => 450000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Booking 2: Returned
        $b2 = Booking::create([
            'booking_code' => 'SKY260909HST02',
            'iphone_id' => $iphone2->id,
            'customer_name' => 'Siti Nurhaliza',
            'customer_phone' => '085712345678',
            'duration' => 48,
            'price' => 700000,
            'status' => 'returned',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Booking 3: Cancelled without payment (should be excluded by default)
        Booking::create([
            'booking_code' => 'SKY260909HST03',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Batal User',
            'customer_phone' => '089999999999',
            'duration' => 24,
            'price' => 300000,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'created' => now(),
        ]);

        $response = $this->getJson('/api/v1/receipts');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'receipt_number',
                        'date',
                        'admin_name',
                        'branch_name',
                        'type',
                        'booking_code',
                        'customer_name',
                        'customer_phone',
                        'unit_name',
                        'rental_duration',
                        'rent_fee',
                        'deposit_fee',
                        'total_amount',
                        'paid_amount',
                        'payment_method',
                        'esc_pos_text',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'summary' => [
                    'total_transactions',
                    'total_rent_fee',
                    'total_deposit_fee',
                    'total_amount',
                    'total_paid',
                ],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('summary.total_transactions'));
    }

    public function test_can_access_via_alias_endpoints(): void
    {
        $iphone = Iphones::factory()->create();
        Booking::create([
            'booking_code' => 'SKY260909HST04',
            'iphone_id' => $iphone->id,
            'customer_name' => 'Alias User',
            'customer_phone' => '081233445566',
            'duration' => 24,
            'price' => 350000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // GET /api/v1/receipts/history
        $resp1 = $this->getJson('/api/v1/receipts/history');
        $resp1->assertStatus(200)
            ->assertJson(['status' => 'success']);
        $this->assertNotEmpty($resp1->json('data'));

        // GET /api/v1/transactions/completed
        $resp2 = $this->getJson('/api/v1/transactions/completed');
        $resp2->assertStatus(200)
            ->assertJson(['status' => 'success']);
        $this->assertNotEmpty($resp2->json('data'));
    }

    public function test_can_search_receipt_history_by_query(): void
    {
        $iphone1 = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max Deep Blue',
            'asset_code' => 'AST-IP15PM-BLUE',
        ]);
        $iphone2 = Iphones::factory()->create([
            'name' => 'iPhone 13 Standard',
            'asset_code' => 'AST-IP13-WHITE',
        ]);

        Booking::create([
            'booking_code' => 'SKY260909SEARCH01',
            'iphone_id' => $iphone1->id,
            'customer_name' => 'Rian Kurniawan',
            'customer_phone' => '087711223344',
            'duration' => 24,
            'price' => 600000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        Booking::create([
            'booking_code' => 'SKY260909SEARCH02',
            'iphone_id' => $iphone2->id,
            'customer_name' => 'Melati Putri',
            'customer_phone' => '087755667788',
            'duration' => 24,
            'price' => 250000,
            'status' => 'rented',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Search by customer name
        $respName = $this->getJson('/api/v1/receipts?q=Kurniawan');
        $respName->assertStatus(200);
        $this->assertCount(1, $respName->json('data'));
        $this->assertEquals('SKY260909SEARCH01', $respName->json('data.0.booking_code'));

        // Search by asset code
        $respAsset = $this->getJson('/api/v1/receipts?q=AST-IP13');
        $respAsset->assertStatus(200);
        $this->assertCount(1, $respAsset->json('data'));
        $this->assertEquals('SKY260909SEARCH02', $respAsset->json('data.0.booking_code'));

        // Search by booking code
        $respCode = $this->getJson('/api/v1/receipts?q=SEARCH01');
        $respCode->assertStatus(200);
        $this->assertCount(1, $respCode->json('data'));
    }

    public function test_can_filter_receipt_history_by_type(): void
    {
        $iphone = Iphones::factory()->create();

        // Rented booking (pickup receipt)
        Booking::create([
            'booking_code' => 'SKY260909TYPE01',
            'iphone_id' => $iphone->id,
            'customer_name' => 'User Pickup',
            'customer_phone' => '081111111111',
            'duration' => 24,
            'price' => 400000,
            'status' => 'rented',
            'picked_up_at' => now(),
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        // Returned booking (returnUnit receipt)
        Booking::create([
            'booking_code' => 'SKY260909TYPE02',
            'iphone_id' => $iphone->id,
            'customer_name' => 'User Return',
            'customer_phone' => '082222222222',
            'duration' => 24,
            'price' => 400000,
            'status' => 'returned',
            'payment_status' => 'paid',
            'created' => now(),
        ]);

        $respPickup = $this->getJson('/api/v1/receipts?type=pickup');
        $respPickup->assertStatus(200);
        $this->assertTrue(collect($respPickup->json('data'))->contains('booking_code', 'SKY260909TYPE01'));

        $respReturn = $this->getJson('/api/v1/receipts?type=returnUnit');
        $respReturn->assertStatus(200);
        $this->assertTrue(collect($respReturn->json('data'))->contains('booking_code', 'SKY260909TYPE02'));
    }

    public function test_pagination_works_correctly(): void
    {
        $iphone = Iphones::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            Booking::create([
                'booking_code' => "SKY260909PAGE0{$i}",
                'iphone_id' => $iphone->id,
                'customer_name' => "Customer {$i}",
                'customer_phone' => "08120000000{$i}",
                'duration' => 24,
                'price' => 300000,
                'status' => 'rented',
                'payment_status' => 'paid',
                'created' => now(),
            ]);
        }

        $response = $this->getJson('/api/v1/receipts?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 2,
                    'total' => 5,
                    'last_page' => 3,
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }
}
