<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Iphones;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IphoneBranchFilterCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_filter_includes_both_direct_and_unassigned_units(): void
    {
        $pusat = Affiliate::factory()->create([
            'name' => 'Genteng Pusat',
            'code' => 'GTG',
            'slug' => 'genteng-pusat',
        ]);

        $branch = Affiliate::factory()->create([
            'name' => 'Affiliate Purwoharjo',
            'code' => 'PWJ',
            'slug' => 'affiliate-purwoharjo',
        ]);

        // 3 unassigned units (belong to pusat)
        Iphones::factory()->count(3)->create(['affiliate_id' => null, 'status' => 'ready']);
        // 2 direct pusat units
        Iphones::factory()->count(2)->create(['affiliate_id' => $pusat->id, 'status' => 'ready']);
        // 4 branch units
        Iphones::factory()->count(4)->create(['affiliate_id' => $branch->id, 'status' => 'ready']);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Test filtering by pusat affiliate_id
        $resPusat = $this->getJson("/api/v1/iphones?affiliate_id={$pusat->id}");
        $resPusat->assertStatus(200);
        $this->assertEquals(5, $resPusat->json('total') ?? count($resPusat->json('data')));
        $this->assertEquals(5, $resPusat->json('summary.total'));

        // Test filtering by pusat branch name
        $resPusatBranch = $this->getJson("/api/v1/iphones?branch=" . urlencode('Genteng Pusat'));
        $resPusatBranch->assertStatus(200);
        $this->assertEquals(5, $resPusatBranch->json('total') ?? count($resPusatBranch->json('data')));
        $this->assertEquals(5, $resPusatBranch->json('summary.total'));

        // Test filtering by non-pusat affiliate_id
        $resBranch = $this->getJson("/api/v1/iphones?affiliate_id={$branch->id}");
        $resBranch->assertStatus(200);
        $this->assertEquals(4, $resBranch->json('total') ?? count($resBranch->json('data')));
        $this->assertEquals(4, $resBranch->json('summary.total'));

        // Test unfiltered returns all 9 units
        $resAll = $this->getJson('/api/v1/iphones');
        $resAll->assertStatus(200);
        $this->assertEquals(9, $resAll->json('total') ?? count($resAll->json('data')));
        $this->assertEquals(9, $resAll->json('summary.total'));
    }
}
