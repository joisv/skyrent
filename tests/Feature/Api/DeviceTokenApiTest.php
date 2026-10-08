<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-dummy-token-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_register_device_token()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-device-a',
            'platform' => 'android',
            'device_name' => 'Pixel 8 Pro',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'token' => 'fcm-token-device-a',
                    'platform' => 'android',
                    'device_name' => 'Pixel 8 Pro',
                ],
            ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-device-a',
            'platform' => 'android',
            'device_name' => 'Pixel 8 Pro',
        ]);
    }

    public function test_user_id_in_payload_is_ignored_and_authenticated_user_is_bound()
    {
        $realUser = User::factory()->create();
        $otherUser = User::factory()->create();

        // Client attempts to spoof otherUser's ID
        $response = $this->actingAs($realUser, 'sanctum')->postJson('/api/v1/device-tokens', [
            'user_id' => $otherUser->id,
            'token' => 'fcm-token-spoof-test',
            'platform' => 'android',
        ]);

        $response->assertOk();

        // Must belong to realUser, NOT otherUser
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $realUser->id,
            'token' => 'fcm-token-spoof-test',
        ]);

        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $otherUser->id,
            'token' => 'fcm-token-spoof-test',
        ]);
    }

    public function test_same_user_can_have_multiple_device_tokens()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-phone',
            'platform' => 'android',
            'device_name' => 'Phone',
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-tablet',
            'platform' => 'android',
            'device_name' => 'Tablet',
        ])->assertOk();

        $this->assertCount(2, DeviceToken::where('user_id', $user->id)->get());
    }

    public function test_re_registering_same_token_does_not_create_duplicate()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-same',
            'platform' => 'android',
            'device_name' => 'Initial Name',
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-same',
            'platform' => 'android',
            'device_name' => 'Updated Name',
        ])->assertOk();

        $this->assertCount(1, DeviceToken::where('token', 'fcm-token-same')->get());
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-token-same',
            'device_name' => 'Updated Name',
        ]);
    }

    public function test_same_device_token_reassigned_when_new_user_logs_in()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 logs in on device
        $this->actingAs($user1, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'shared-tablet-token',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user1->id,
            'token' => 'shared-tablet-token',
        ]);

        // User 2 logs into the same device later
        $this->actingAs($user2, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'shared-tablet-token',
        ])->assertOk();

        // Token should now be assigned to user 2
        $this->assertCount(1, DeviceToken::where('token', 'shared-tablet-token')->get());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user2->id,
            'token' => 'shared-tablet-token',
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user1->id,
            'token' => 'shared-tablet-token',
        ]);
    }

    public function test_token_refresh_removes_old_token_and_adds_new_token()
    {
        $user = User::factory()->create();

        // Initial token
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'initial-token-111',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'initial-token-111',
        ]);

        // Token refreshed
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'refreshed-token-222',
            'old_token' => 'initial-token-111',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'initial-token-111',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'refreshed-token-222',
        ]);
    }

    public function test_destroy_endpoint_removes_device_token()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token-to-delete',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/device-tokens', [
            'token' => 'token-to-delete',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'deleted' => true,
                ],
            ]);

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'token-to-delete',
        ]);
    }

    public function test_logout_with_device_token_removes_token()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token-logout-clean',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/logout', [
            'device_token' => 'token-logout-clean',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'token-logout-clean',
        ]);
    }

    public function test_send_test_notification_to_user_devices()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'test-device-token-123',
            'platform' => 'android',
        ]);

        $mockFcm = \Mockery::mock(\App\Services\FcmService::class);
        $mockFcm->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function ($argUser, $title, $body, $data) use ($user) {
                return $argUser->id === $user->id
                    && $title === 'SkyRental Test'
                    && $body === 'Push notification berhasil';
            })
            ->andReturn([
                'user_id' => $user->id,
                'total' => 1,
                'success_count' => 1,
                'failure_count' => 0,
            ]);

        $this->app->instance(\App\Services\FcmService::class, $mockFcm);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens/test', [
            'title' => 'SkyRental Test',
            'body' => 'Push notification berhasil',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 1,
                    'success_count' => 1,
                ],
            ]);
    }
}

