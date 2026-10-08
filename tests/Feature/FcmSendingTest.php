<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmSendingTest extends TestCase
{
    use RefreshDatabase;

    protected FcmService $fcmService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcmService = app(FcmService::class);
    }

    public function test_send_to_user_with_active_device_tokens()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-pixel-1',
            'platform' => 'android',
            'device_name' => 'Pixel 8',
        ]);

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-iphone-1',
            'platform' => 'ios',
            'device_name' => 'iPhone 15',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response([
                'name' => 'projects/skyrental-admin/messages/mock_message_id_123',
            ], 200),
        ]);

        $result = $this->fcmService->sendToUser(
            $user,
            'Booking Confirmed',
            'Your booking #BK123 has been approved.',
            ['booking_id' => 123, 'status' => 'confirmed']
        );

        $this->assertEquals($user->id, $result['user_id']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['success_count']);
        $this->assertEquals(0, $result['failure_count']);
        $this->assertCount(2, $result['results']);

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            return isset($data['message']['token'])
                && in_array($data['message']['token'], ['fcm-token-pixel-1', 'fcm-token-iphone-1'])
                && $data['message']['notification']['title'] === 'Booking Confirmed'
                && $data['message']['notification']['body'] === 'Your booking #BK123 has been approved.'
                && $data['message']['data']['booking_id'] === '123'
                && $data['message']['data']['status'] === 'confirmed';
        });
    }

    public function test_send_to_user_using_uuid_string()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-test-user-id',
            'platform' => 'android',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response([
                'name' => 'projects/skyrental-admin/messages/mock_message_id_456',
            ], 200),
        ]);

        $result = $this->fcmService->sendToUser(
            $user->id,
            'Test Title',
            'Test Body'
        );

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(0, $result['failure_count']);
    }

    public function test_send_to_user_without_device_tokens_gracefully_returns_zero()
    {
        $user = User::factory()->create();

        Http::fake();

        $result = $this->fcmService->sendToUser($user, 'No Token Title', 'No Token Body');

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['success_count']);
        $this->assertEquals(0, $result['failure_count']);
        $this->assertEmpty($result['results']);

        Http::assertNothingSent();
    }

    public function test_send_to_multiple_users()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create(); // no tokens

        DeviceToken::create([
            'user_id' => $userA->id,
            'token' => 'token-a1',
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $userB->id,
            'token' => 'token-b1',
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $userB->id,
            'token' => 'token-b2',
            'platform' => 'ios',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'mock'], 200),
        ]);

        $aggregated = $this->fcmService->sendToUsers(
            [$userA, $userB, $userC],
            'Broadcast Title',
            'Broadcast Message'
        );

        $this->assertEquals(3, $aggregated['total_users']);
        $this->assertEquals(3, $aggregated['total_tokens']);
        $this->assertEquals(3, $aggregated['success_count']);
        $this->assertEquals(0, $aggregated['failure_count']);
        $this->assertArrayHasKey($userA->id, $aggregated['results']);
        $this->assertArrayHasKey($userB->id, $aggregated['results']);
        $this->assertArrayHasKey($userC->id, $aggregated['results']);
        $this->assertEquals(0, $aggregated['results'][$userC->id]['total']);
    }

    public function test_unregistered_or_invalid_v1_token_is_automatically_deleted()
    {
        $user = User::factory()->create();

        $validToken = 'fcm-valid-token';
        $staleToken = 'fcm-unregistered-token';

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $validToken,
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $staleToken,
            'platform' => 'android',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => function (Request $request) use ($staleToken) {
                $body = $request->data();
                if (($body['message']['token'] ?? '') === $staleToken) {
                    return Http::response([
                        'error' => [
                            'code' => 404,
                            'message' => 'Requested entity was not found.',
                            'status' => 'NOT_FOUND',
                            'details' => [
                                [
                                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                                    'errorCode' => 'UNREGISTERED',
                                ],
                            ],
                        ],
                    ], 404);
                }

                return Http::response(['name' => 'projects/skyrental-admin/messages/ok'], 200);
            },
        ]);

        $result = $this->fcmService->sendToUser($user, 'Title', 'Body');

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(1, $result['failure_count']);

        // Check database: stale token should be deleted automatically
        $this->assertDatabaseMissing('device_tokens', [
            'token' => $staleToken,
        ]);

        // Valid token remains in database
        $this->assertDatabaseHas('device_tokens', [
            'token' => $validToken,
        ]);
    }

    public function test_legacy_fcm_sending_and_token_cleanup()
    {
        config([
            'services.firebase.credentials' => null,
            'services.firebase.server_key' => 'fake-server-key-legacy',
        ]);

        $user = User::factory()->create();

        $validToken = 'legacy-valid-token';
        $badToken = 'legacy-not-registered-token';

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $validToken,
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $badToken,
            'platform' => 'android',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/fcm/send' => function (Request $request) use ($badToken) {
                $body = $request->data();
                if (($body['to'] ?? '') === $badToken) {
                    return Http::response([
                        'multicast_id' => 12345,
                        'success' => 0,
                        'failure' => 1,
                        'results' => [
                            ['error' => 'NotRegistered'],
                        ],
                    ], 200);
                }

                return Http::response([
                    'multicast_id' => 12345,
                    'success' => 1,
                    'failure' => 0,
                    'results' => [
                        ['message_id' => '0:123456789'],
                    ],
                ], 200);
            },
        ]);

        $result = $this->fcmService->sendToUser($user, 'Legacy Title', 'Legacy Body');

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(1, $result['failure_count']);

        // Verify headers used server key
        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'key=fake-server-key-legacy');
        });

        // Verify bad token was removed
        $this->assertDatabaseMissing('device_tokens', ['token' => $badToken]);
        $this->assertDatabaseHas('device_tokens', ['token' => $validToken]);
    }

    public function test_format_data_payload_stringifies_all_values()
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-data-format-token',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
        ]);

        $this->fcmService->sendToUser($user, 'Title', 'Body', [
            'booking_id' => 456,
            'is_paid' => true,
            'is_pending' => false,
            'notes' => null,
            'meta' => ['foo' => 'bar'],
        ]);

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message']['data'] ?? [];
            return $data['booking_id'] === '456'
                && $data['is_paid'] === '1'
                && $data['is_pending'] === '0'
                && $data['notes'] === ''
                && $data['meta'] === '{"foo":"bar"}';
        });
    }
}
