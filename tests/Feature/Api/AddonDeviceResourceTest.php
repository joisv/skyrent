<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Gallery;
use App\Models\Iphones;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddonDeviceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Affiliate $affiliate;
    protected Gallery $gallery;

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

        $this->gallery = Gallery::create([
            'id' => 1,
            'image' => 'default.jpg',
        ]);
    }

    public function test_tripod_addon_device_is_identified_as_addon(): void
    {
        // Create an iPhone unit
        $iphone = Iphones::create([
            'name' => 'iPhone 15 Pro',
            'asset_code' => 'IPHSKY1001',
            'serial_number' => 'DX1001',
            'status' => 'ready',
            'slug' => 'iphone-15-pro',
            'gallery_id' => $this->gallery->id,
            'user_id' => $this->user->id,
        ]);

        // Create an Addon device (Tripod)
        $tripod = Iphones::create([
            'name' => 'tripod',
            'asset_code' => 'ADDON1310',
            'serial_number' => 'ACC1A17CAEBEC0',
            'status' => 'ready',
            'slug' => 'tripod',
            'gallery_id' => $this->gallery->id,
            'user_id' => $this->user->id,
        ]);

        $res = $this->getJson('/api/v1/iphones');
        $res->assertStatus(200);

        $data = collect($res->json('data'));

        $iphoneItem = $data->firstWhere('asset_code', 'IPHSKY1001');
        $tripodItem = $data->firstWhere('asset_code', 'ADDON1310');

        $this->assertNotNull($iphoneItem);
        $this->assertFalse($iphoneItem['is_addon']);

        $this->assertNotNull($tripodItem);
        $this->assertTrue($tripodItem['is_addon']);
    }
}
