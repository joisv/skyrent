<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Store or update an FCM device token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'old_token' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // If an old token was provided during a token refresh, delete it
        if (!empty($validated['old_token']) && $validated['old_token'] !== $validated['token']) {
            DeviceToken::where('token', $validated['old_token'])
                ->where('user_id', $user->id)
                ->delete();
        }

        // Never trust user_id from the client; always bind to the authenticated user.
        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'FCM device token registered successfully.',
            'data' => [
                'id' => $deviceToken->id,
                'user_id' => $deviceToken->user_id,
                'token' => $deviceToken->token,
                'platform' => $deviceToken->platform,
                'device_name' => $deviceToken->device_name,
                'updated_at' => $deviceToken->updated_at,
            ],
        ], 200);
    }

    /**
     * Remove / deactivate an FCM device token upon logout or explicit unregister.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $deletedCount = DeviceToken::where('token', $validated['token'])
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'FCM device token removed successfully.',
            'data' => [
                'deleted' => $deletedCount > 0,
            ],
        ], 200);
    }

    /**
     * Send a test push notification to the authenticated user's registered devices.
     */
    public function sendTest(Request $request, FcmService $fcmService): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
            'data' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $title = $validated['title'] ?? 'SkyRental Test';
        $body = $validated['body'] ?? 'Push notification berhasil';
        $data = $validated['data'] ?? [
            'type' => 'test_notification',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'route' => '/',
        ];

        $result = $fcmService->sendToUser($user, $title, $body, $data);

        return response()->json([
            'success' => true,
            'message' => 'Test notification processed.',
            'data' => $result,
        ], 200);
    }
}

