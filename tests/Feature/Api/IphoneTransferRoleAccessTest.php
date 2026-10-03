<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Iphones;
use App\Models\IphoneTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IphoneTransferRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Affiliate $affiliateCentral;
    protected Affiliate $affiliateA;
    protected Affiliate $affiliateB;
    protected User $affiliateAdminA;
    protected User $affiliateAdminB;
    protected Iphones $iphone1;
    protected Iphones $iphone2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'affiliate-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->affiliateCentral = Affiliate::factory()->create([
            'name' => 'Affiliate Pusat',
            'code' => 'PST',
            'slug' => 'affiliate-pusat',
        ]);

        $this->affiliateA = Affiliate::factory()->create([
            'name' => 'Affiliate Cabang A',
            'code' => 'CBA',
            'slug' => 'cabang-a',
        ]);

        $this->affiliateB = Affiliate::factory()->create([
            'name' => 'Affiliate Cabang B',
            'code' => 'CBB',
            'slug' => 'cabang-b',
        ]);

        $this->affiliateAdminA = User::factory()->create([
            'name' => 'Admin Cabang A',
            'email' => 'admin.a@example.com',
            'affiliate_id' => $this->affiliateA->id,
        ]);
        $this->affiliateAdminA->assignRole('affiliate-admin');

        $this->affiliateAdminB = User::factory()->create([
            'name' => 'Admin Cabang B',
            'email' => 'admin.b@example.com',
            'affiliate_id' => $this->affiliateB->id,
        ]);
        $this->affiliateAdminB->assignRole('affiliate-admin');

        $this->iphone1 = Iphones::factory()->create([
            'name' => 'iPhone 13 Pro 128GB',
            'status' => 'transferred',
            'affiliate_id' => $this->affiliateCentral->id,
        ]);

        $this->iphone2 = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max 256GB',
            'status' => 'transferred',
            'affiliate_id' => $this->affiliateCentral->id,
        ]);
    }

    public function test_affiliate_admin_only_sees_transfers_destined_for_their_affiliate(): void
    {
        // Transfer 1 ditujukan ke Cabang A
        $transferA = IphoneTransfer::create([
            'iphone_id' => $this->iphone1->id,
            'from_affiliate_id' => $this->affiliateCentral->id,
            'to_affiliate_id' => $this->affiliateA->id,
            'sent_by' => $this->affiliateAdminA->id,
            'status' => 'in_transit',
            'sent_at' => now(),
        ]);

        // Transfer 2 ditujukan ke Cabang B
        $transferB = IphoneTransfer::create([
            'iphone_id' => $this->iphone2->id,
            'from_affiliate_id' => $this->affiliateCentral->id,
            'to_affiliate_id' => $this->affiliateB->id,
            'sent_by' => $this->affiliateAdminB->id,
            'status' => 'in_transit',
            'sent_at' => now(),
        ]);

        // Login sebagai Admin Cabang A
        Sanctum::actingAs($this->affiliateAdminA);

        $response = $this->getJson('/api/v1/affiliates/transfers');
        $response->assertStatus(200);

        $transferIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($transferA->id, $transferIds);
        $this->assertNotContains($transferB->id, $transferIds);

        // Bahkan jika Admin Cabang A memanipulasi parameter query affiliate_id=B
        $tamperedResponse = $this->getJson('/api/v1/affiliates/transfers?affiliate_id=' . $this->affiliateB->id);
        $tamperedResponse->assertStatus(200);

        $tamperedIds = collect($tamperedResponse->json('data'))->pluck('id')->toArray();
        $this->assertContains($transferA->id, $tamperedIds);
        $this->assertNotContains($transferB->id, $tamperedIds);
    }

    public function test_affiliate_admin_cannot_accept_transfer_for_different_affiliate_returns_403(): void
    {
        // Transfer ditujukan ke Cabang B
        $transferB = IphoneTransfer::create([
            'iphone_id' => $this->iphone2->id,
            'from_affiliate_id' => $this->affiliateCentral->id,
            'to_affiliate_id' => $this->affiliateB->id,
            'sent_by' => $this->affiliateAdminB->id,
            'status' => 'in_transit',
            'sent_at' => now(),
        ]);

        // Admin Cabang A mencoba menerima transfer milik Cabang B
        Sanctum::actingAs($this->affiliateAdminA);

        $response = $this->postJson("/api/v1/affiliates/transfers/{$transferB->id}/accept");
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Anda tidak memiliki hak akses untuk menerima transfer iPhone ini.',
        ]);

        // Pastikan transfer tidak berubah
        $transferB->refresh();
        $this->assertEquals('in_transit', $transferB->status);
    }

    public function test_affiliate_admin_can_accept_transfer_for_their_affiliate(): void
    {
        // Transfer ditujukan ke Cabang A
        $transferA = IphoneTransfer::create([
            'iphone_id' => $this->iphone1->id,
            'from_affiliate_id' => $this->affiliateCentral->id,
            'to_affiliate_id' => $this->affiliateA->id,
            'sent_by' => $this->affiliateAdminB->id,
            'status' => 'in_transit',
            'sent_at' => now(),
        ]);

        // Admin Cabang A menerima transfer
        Sanctum::actingAs($this->affiliateAdminA);

        $response = $this->postJson("/api/v1/affiliates/transfers/{$transferA->id}/accept");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verifikasi transfer ter-update
        $transferA->refresh();
        $this->assertEquals('received', $transferA->status);
        $this->assertEquals($this->affiliateAdminA->id, $transferA->received_by);
        $this->assertNotNull($transferA->received_at);

        // Verifikasi iPhone ter-update
        $this->iphone1->refresh();
        $this->assertEquals('ready', $this->iphone1->status);
        $this->assertEquals($this->affiliateA->id, $this->iphone1->affiliate_id);
    }

    public function test_accepting_already_received_transfer_returns_422(): void
    {
        $transferA = IphoneTransfer::create([
            'iphone_id' => $this->iphone1->id,
            'from_affiliate_id' => $this->affiliateCentral->id,
            'to_affiliate_id' => $this->affiliateA->id,
            'sent_by' => $this->affiliateAdminB->id,
            'received_by' => $this->affiliateAdminA->id,
            'status' => 'received',
            'sent_at' => now()->subDay(),
            'received_at' => now()->subHours(2),
        ]);

        Sanctum::actingAs($this->affiliateAdminA);

        $response = $this->postJson("/api/v1/affiliates/transfers/{$transferA->id}/accept");
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'iPhone pada transfer ini sudah diterima sebelumnya.',
        ]);
    }
}
