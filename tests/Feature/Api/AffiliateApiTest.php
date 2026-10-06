<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\Iphones;
use App\Models\IphoneTransfer;
use App\Models\ShopSetting;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShopSettingsSeeder::class);
    }

    public function test_can_get_affiliate_list_and_summary(): void
    {
        $aff = Affiliate::create([
            'code' => 'BWI',
            'name' => 'Affiliate Banyuwangi Kota',
            'slug' => 'affiliate-banyuwangi-kota',
            'city' => 'Banyuwangi',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/affiliates');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_affiliates' => 1,
                    'active_affiliates' => 1,
                ],
            ])
            ->assertJsonFragment([
                'id' => $aff->id,
                'code' => 'BWI',
                'name' => 'Affiliate Banyuwangi Kota',
            ]);
    }

    public function test_can_create_new_affiliate(): void
    {
        $payload = [
            'code' => 'SBY',
            'name' => 'Cabang Surabaya Gubeng',
            'phone' => '081234567890',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/affiliates', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'SBY',
                    'name' => 'Cabang Surabaya Gubeng',
                    'city' => 'Surabaya',
                ],
            ]);

        $this->assertDatabaseHas('affiliates', ['code' => 'SBY']);
    }

    public function test_can_get_affiliate_detail_by_id(): void
    {
        $aff = Affiliate::create([
            'code' => 'YOG',
            'name' => 'Affiliate Yogyakarta',
            'slug' => 'affiliate-yogyakarta',
            'city' => 'Yogyakarta',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/affiliates/{$aff->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $aff->id,
                    'code' => 'YOG',
                    'name' => 'Affiliate Yogyakarta',
                ],
            ]);
    }

    public function test_can_update_affiliate(): void
    {
        $aff = Affiliate::create([
            'code' => 'SMG',
            'name' => 'Affiliate Semarang',
            'slug' => 'affiliate-semarang',
            'city' => 'Semarang',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/affiliates/{$aff->id}", [
            'name' => 'Affiliate Semarang Simpang Lima',
            'phone' => '081987654321',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Affiliate Semarang Simpang Lima',
                    'phone' => '081987654321',
                ],
            ]);
    }

    public function test_can_delete_affiliate_without_active_bookings(): void
    {
        $aff = Affiliate::create([
            'code' => 'DPS',
            'name' => 'Affiliate Denpasar Bali',
            'slug' => 'affiliate-denpasar-bali',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/affiliates/{$aff->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('affiliates', ['id' => $aff->id]);
    }

    public function test_can_create_and_accept_iphone_transfer(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'super-admin']);
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $affA = Affiliate::create(['code' => 'AFA', 'name' => 'Affiliate Asal', 'slug' => 'affiliate-asal']);
        $affB = Affiliate::create(['code' => 'AFB', 'name' => 'Affiliate Tujuan', 'slug' => 'affiliate-tujuan']);

        $iphone = Iphones::factory()->create([
            'status' => 'ready',
            'affiliate_id' => $affA->id,
        ]);

        // Create transfer
        $createRes = $this->postJson('/api/v1/affiliates/transfers', [
            'iphone_id' => $iphone->id,
            'from_affiliate_id' => $affA->id,
            'to_affiliate_id' => $affB->id,
            'notes' => 'Mutasi untuk kebutuhan event',
        ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'in_transit',
                ],
            ]);

        $transferId = $createRes->json('data.id');

        // Check list transfers
        $listRes = $this->getJson('/api/v1/affiliates/transfers');
        $listRes->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'iphone_id', 'status']]]);

        // Accept transfer
        $acceptRes = $this->postJson("/api/v1/affiliates/transfers/{$transferId}/accept");
        $acceptRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'received',
                ],
            ]);

        $this->assertEquals($affB->id, $iphone->fresh()->affiliate_id);
    }

    public function test_can_get_affiliate_revenue_report(): void
    {
        $aff = Affiliate::create(['code' => 'REV', 'name' => 'Affiliate Revenue Test', 'slug' => 'affiliate-rev']);

        $response = $this->getJson("/api/v1/affiliates/{$aff->id}/revenue");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'affiliate_id' => $aff->id,
                    'affiliate_code' => 'REV',
                    'affiliate_revenue' => 0,
                    'payments' => [],
                ],
            ]);
    }

    public function test_can_assign_users_to_affiliate_and_list_users(): void
    {
        $aff = Affiliate::create(['code' => 'USR', 'name' => 'Affiliate User Test', 'slug' => 'affiliate-user-test']);
        $user1 = User::factory()->create(['name' => 'Staff 1', 'email' => 'staff1@skyrent.id']);
        $user2 = User::factory()->create(['name' => 'Staff 2', 'email' => 'staff2@skyrent.id']);

        // Check available users
        $availRes = $this->getJson("/api/v1/affiliates/{$aff->id}/available-users");
        $availRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Assign users
        $assignRes = $this->postJson("/api/v1/affiliates/{$aff->id}/users", [
            'user_ids' => [$user1->id, $user2->id],
        ]);

        $assignRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals($aff->id, $user1->fresh()->affiliate_id);
        $this->assertEquals($aff->id, $user2->fresh()->affiliate_id);

        // List assigned users
        $listUsersRes = $this->getJson("/api/v1/affiliates/{$aff->id}/users");
        $listUsersRes->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Remove user 1
        $removeRes = $this->deleteJson("/api/v1/affiliates/{$aff->id}/users/{$user1->id}");
        $removeRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNull($user1->fresh()->affiliate_id);
    }

    public function test_pusat_affiliate_includes_unassigned_iphones_and_bookings(): void
    {
        Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $pusat = Affiliate::create([
            'code' => 'GTG PUSAT',
            'name' => 'Pusat',
            'slug' => 'pusat',
            'is_active' => true,
        ]);
        $superAdmin->update(['affiliate_id' => $pusat->id]);

        // 2 iphones with affiliate_id null (pusat inventory)
        $iphone1 = Iphones::factory()->create(['name' => 'iPhone 13 Pink', 'affiliate_id' => null, 'status' => 'ready']);
        $iphone2 = Iphones::factory()->create(['name' => 'iPhone 13 Aja', 'affiliate_id' => null, 'status' => 'ready']);

        // 1 booking with affiliate_id null and user_id superAdmin
        $booking = Booking::factory()->create([
            'booking_code' => 'SKYTEST001',
            'affiliate_id' => null,
            'user_id' => $superAdmin->id,
            'iphone_id' => $iphone1->id,
        ]);

        // Check index
        $indexRes = $this->getJson('/api/v1/affiliates');
        $indexRes->assertStatus(200);
        $item = collect($indexRes->json('data'))->firstWhere('id', $pusat->id);
        $this->assertNotNull($item);
        $this->assertEquals(2, $item['iphones_count']);
        $this->assertEquals(1, $item['bookings_count']);

        // Check detail
        $showRes = $this->getJson("/api/v1/affiliates/{$pusat->id}");
        $showRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpi' => [
                        'iphones_count' => 2,
                        'bookings_count' => 1,
                    ],
                ],
            ]);
        $this->assertCount(2, $showRes->json('data.iphones'));
        $this->assertCount(1, $showRes->json('data.recent_bookings'));

        // Check dedicated endpoints
        $iphonesRes = $this->getJson("/api/v1/affiliates/{$pusat->id}/iphones");
        $iphonesRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $bookingsRes = $this->getJson("/api/v1/affiliates/{$pusat->id}/bookings");
        $bookingsRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
    }
}
