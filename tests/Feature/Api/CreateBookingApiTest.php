<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\Iphones;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_booking_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_name',
                'customer_phone',
                'iphone_id',
                'start_booking_date',
                'end_booking_date',
                'price',
            ]);
    }

    public function test_can_create_walk_in_booking_successfully(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro 128GB',
            'status' => 'ready',
        ]);

        $payload = [
            'customer_name' => 'Rian Pratama',
            'customer_phone' => '081234567899',
            'customer_email' => 'rian@example.com',
            'address' => 'Jl. Kaliurang KM 5, Sleman',
            'iphone_id' => $iphone->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDays(2)->toDateString(),
            'duration' => 2,
            'price' => 380000,
            'deposit' => 200000,
            'jaminan_type' => 'KTP Asli',
            'pickup_type' => 'Ambil di Outlet',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Booking baru berhasil dibuat.',
                'data' => [
                    'customer_name' => 'Rian Pratama',
                    'customer_phone' => '081234567899',
                    'price' => 380000,
                    'payment_status' => 'unpaid',
                    'iphone' => [
                        'name' => 'iPhone 15 Pro 128GB',
                    ],
                ],
            ]);

        $bookingCode = $response->json('data.booking_code');
        $this->assertNotEmpty($bookingCode);
        $this->assertStringStartsWith('SKY', $bookingCode);

        $this->assertDatabaseHas('bookings', [
            'booking_code' => $bookingCode,
            'customer_name' => 'Rian Pratama',
            'status' => 'confirmed',
        ]);
    }

    public function test_can_create_booking_with_initial_payment(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $iphone = Iphones::factory()->create([
            'name' => 'iPhone 14 Pro Max',
            'status' => 'ready',
        ]);

        $payload = [
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '081987654321',
            'iphone_id' => $iphone->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDays(1)->toDateString(),
            'duration' => 1,
            'price' => 200000,
            'deposit' => 200000,
            'amount_paid' => 200000,
            'payment_method' => 'QRIS',
        ];

        $response = $this->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'price' => 200000,
                    'total_paid' => 200000,
                    'remaining_payment' => 0,
                    'payment_status' => 'paid',
                ],
            ]);

        $bookingId = $response->json('data.id');
        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $bookingId,
            'amount' => 200000,
            'type' => 'payment',
        ]);
    }

    public function test_can_create_booking_via_alias_route(): void
    {
        $iphone = Iphones::factory()->create();

        $payload = [
            'customer_name' => 'Fajar Nugroho',
            'customer_phone' => '085678901234',
            'iphone_id' => $iphone->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDays(3)->toDateString(),
            'price' => 450000,
        ];

        $response = $this->postJson('/api/v1/bookings/create', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_affiliate_user_cannot_book_iphone_belonging_to_another_affiliate_returns_403(): void
    {
        $this->seed(ShopSettingsSeeder::class);
        Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'web']);

        $affiliate1 = Affiliate::factory()->create();
        $affiliate2 = Affiliate::factory()->create();

        $affiliateUser = User::factory()->create(['affiliate_id' => $affiliate1->id]);
        $affiliateUser->assignRole('affiliate');

        $iphoneAff2 = Iphones::factory()->create([
            'affiliate_id' => $affiliate2->id,
            'status' => 'ready',
        ]);

        $payload = [
            'customer_name' => 'Budi Cross',
            'customer_phone' => '081234567890',
            'iphone_id' => $iphoneAff2->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDays(2)->toDateString(),
            'price' => 300000,
        ];

        $response = $this->actingAs($affiliateUser, 'sanctum')->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak: Unit iPhone ini tidak terdaftar pada cabang/affiliate Anda.',
            ]);
    }

    public function test_affiliate_user_can_book_their_own_affiliate_iphone(): void
    {
        $this->seed(ShopSettingsSeeder::class);
        Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'web']);

        $affiliate = Affiliate::factory()->create();

        $affiliateUser = User::factory()->create(['affiliate_id' => $affiliate->id]);
        $affiliateUser->assignRole('affiliate');

        $iphone = Iphones::factory()->create([
            'affiliate_id' => $affiliate->id,
            'status' => 'ready',
        ]);

        $payload = [
            'customer_name' => 'Budi Own Affiliate',
            'customer_phone' => '081234567890',
            'iphone_id' => $iphone->id,
            'start_booking_date' => now()->toDateString(),
            'end_booking_date' => now()->addDays(2)->toDateString(),
            'price' => 300000,
        ];

        $response = $this->actingAs($affiliateUser, 'sanctum')->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_cannot_book_iphone_when_schedule_conflicts_returns_422(): void
    {
        $this->seed(ShopSettingsSeeder::class);

        $iphone = Iphones::factory()->create(['status' => 'ready']);

        // Create an active confirmed booking for this unit
        Booking::factory()->create([
            'iphone_id' => $iphone->id,
            'status' => 'confirmed',
            'start_booking_date' => now()->addDays(1)->startOfDay(),
            'end_booking_date' => now()->addDays(3)->endOfDay(),
        ]);

        $payload = [
            'customer_name' => 'Overlapping Customer',
            'customer_phone' => '081234567890',
            'iphone_id' => $iphone->id,
            'start_booking_date' => now()->addDays(2)->toDateString(),
            'end_booking_date' => now()->addDays(4)->toDateString(),
            'price' => 300000,
        ];

        $response = $this->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
        $this->assertStringContainsString('tidak tersedia untuk jadwal yang dipilih', $response->json('message'));
    }
}