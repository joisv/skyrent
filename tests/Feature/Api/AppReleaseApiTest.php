<?php

namespace Tests\Feature\Api;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'affiliate']);
    }

    public function test_check_update_when_no_active_release(): void
    {
        $response = $this->getJson('/api/v1/app/check-update?current_build=1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'has_update' => false,
                'is_mandatory' => false,
                'data' => null,
            ]);
    }

    public function test_check_update_returns_latest_active_release(): void
    {
        // Dummy file in storage
        Storage::disk('local')->put('apks/test_v101.apk', 'APK_BINARY_CONTENT');
        $checksum = hash('sha256', 'APK_BINARY_CONTENT');

        AppRelease::create([
            'version_name' => '1.0.1',
            'build_number' => 2,
            'min_supported_build' => 1,
            'release_notes' => 'Pembaruan fitur cetak resi dan stabilitas.',
            'apk_path' => 'apks/test_v101.apk',
            'apk_size' => 18,
            'apk_checksum_sha256' => $checksum,
            'is_active' => true,
        ]);

        // Client on build 1 checks for update
        $response = $this->getJson('/api/v1/app/check-update?current_build=1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'has_update' => true,
                'is_mandatory' => false,
                'data' => [
                    'latest_version' => '1.0.1',
                    'latest_build' => 2,
                    'min_supported_build' => 1,
                    'release_notes' => 'Pembaruan fitur cetak resi dan stabilitas.',
                    'checksum_sha256' => $checksum,
                    'file_size' => 18,
                ],
            ]);

        $this->assertStringContainsString('/api/v1/app/download/2', $response->json('data.download_url'));
    }

    public function test_check_update_when_client_already_up_to_date(): void
    {
        AppRelease::create([
            'version_name' => '1.0.1',
            'build_number' => 2,
            'release_notes' => 'Notes',
            'apk_path' => 'apks/test.apk',
            'apk_size' => 100,
            'apk_checksum_sha256' => str_repeat('a', 64),
            'is_active' => true,
        ]);

        // Client on build 2
        $response = $this->getJson('/api/v1/app/check-update?current_build=2');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'has_update' => false,
            ]);

        // Client on higher build 3
        $responseHigher = $this->getJson('/api/v1/app/check-update?current_build=3');
        $responseHigher->assertStatus(200)->assertJson(['has_update' => false]);
    }

    public function test_mandatory_update_flag_when_below_min_supported_build(): void
    {
        AppRelease::create([
            'version_name' => '2.0.0',
            'build_number' => 10,
            'min_supported_build' => 5, // Requires build >= 5
            'release_notes' => 'Critical security update',
            'apk_path' => 'apks/test_v2.apk',
            'apk_size' => 500,
            'apk_checksum_sha256' => str_repeat('b', 64),
            'is_active' => true,
        ]);

        // Client on build 3 (< 5) -> mandatory
        $respMandatory = $this->getJson('/api/v1/app/check-update?current_build=3');
        $respMandatory->assertStatus(200)
            ->assertJson([
                'has_update' => true,
                'is_mandatory' => true,
            ]);

        // Client on build 6 (>= 5, but < 10) -> optional update
        $respOptional = $this->getJson('/api/v1/app/check-update?current_build=6');
        $respOptional->assertStatus(200)
            ->assertJson([
                'has_update' => true,
                'is_mandatory' => false,
            ]);
    }

    public function test_inactive_releases_are_ignored(): void
    {
        AppRelease::create([
            'version_name' => '1.0.5',
            'build_number' => 5,
            'apk_path' => 'apks/inactive.apk',
            'apk_size' => 100,
            'apk_checksum_sha256' => str_repeat('c', 64),
            'is_active' => false, // Inactive
        ]);

        $response = $this->getJson('/api/v1/app/check-update?current_build=1');
        $response->assertStatus(200)->assertJson(['has_update' => false]);
    }

    public function test_download_active_apk_file(): void
    {
        Storage::disk('local')->put('apks/skymobile_build_2.apk', 'VALID_BINARY_BYTES');
        $checksum = hash('sha256', 'VALID_BINARY_BYTES');

        AppRelease::create([
            'version_name' => '1.0.1',
            'build_number' => 2,
            'apk_path' => 'apks/skymobile_build_2.apk',
            'apk_size' => 18,
            'apk_checksum_sha256' => $checksum,
            'is_active' => true,
        ]);

        $response = $this->get('/api/v1/app/download/2');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        $response->assertHeader('X-Checksum-SHA256', $checksum);
    }

    public function test_download_latest_active_apk(): void
    {
        Storage::disk('local')->put('apks/skymobile_build_10.apk', 'LATEST_BINARY_BYTES');
        $checksum = hash('sha256', 'LATEST_BINARY_BYTES');

        AppRelease::create([
            'version_name' => '1.5.0',
            'build_number' => 10,
            'apk_path' => 'apks/skymobile_build_10.apk',
            'apk_size' => 19,
            'apk_checksum_sha256' => $checksum,
            'is_active' => true,
        ]);

        $response = $this->get('/api/v1/app/download/latest');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        $response->assertHeader('X-Checksum-SHA256', $checksum);
    }

    public function test_download_fails_when_release_not_found_or_inactive(): void
    {
        $response404 = $this->getJson('/api/v1/app/download/999');
        $response404->assertStatus(404);
    }

    public function test_admin_settings_access_authorization(): void
    {
        $guest = $this->get('/admin/settings/app-updates');
        $guest->assertRedirect('/login');

        $affiliate = User::factory()->create();
        $affiliate->assignRole('affiliate');
        $affResponse = $this->actingAs($affiliate)->get('/admin/settings/app-updates');
        $affResponse->assertStatus(403);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $adminResponse = $this->actingAs($admin)->get('/admin/settings/app-updates');
        $adminResponse->assertStatus(200);
    }
}
