<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\IphoneTransfer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Send push notification to a specific user.
     *
     * @param User|string $user User model instance or user UUID string
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload
     * @return array Summary of sending results
     */
    public function sendToUser(User|string $user, string $title, string $body, array $data = []): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        $tokens = DeviceToken::where('user_id', $userId)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return [
                'user_id' => $userId,
                'total' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'results' => [],
            ];
        }

        $result = $this->sendToTokens($tokens, $title, $body, $data);
        $result['user_id'] = $userId;

        return $result;
    }

    /**
     * Send push notification to multiple users.
     *
     * @param iterable $users Collection or array of User models or user UUID strings
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload
     * @return array Aggregated results
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): array
    {
        $aggregated = [
            'total_users' => 0,
            'total_tokens' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'results' => [],
        ];

        foreach ($users as $user) {
            $userResult = $this->sendToUser($user, $title, $body, $data);
            $aggregated['total_users']++;
            $aggregated['total_tokens'] += $userResult['total'];
            $aggregated['success_count'] += $userResult['success_count'];
            $aggregated['failure_count'] += $userResult['failure_count'];
            $aggregated['results'][$userResult['user_id']] = $userResult;
        }

        return $aggregated;
    }

    /**
     * Send push notification to an array of FCM tokens.
     *
     * @param array $tokens Array of FCM device tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload
     * @return array
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($tokens as $token) {
            $response = $this->sendToToken($token, $title, $body, $data);
            $results[] = $response;

            if ($response['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'total' => count($tokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results,
        ];
    }

    /**
     * Send push notification to a single FCM device token.
     *
     * @param string $token Device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload
     * @return array Result of sending attempt
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $credentials = config('services.firebase.credentials');
        $serverKey = config('services.firebase.server_key');

        if ($credentials) {
            return $this->sendViaHttpV1($token, $title, $body, $data);
        }

        if ($serverKey) {
            return $this->sendViaLegacy($token, $title, $body, $data, $serverKey);
        }

        // Neither credentials nor server_key configured: try HTTP v1 if project_id is present
        return $this->sendViaHttpV1($token, $title, $body, $data);
    }

    /**
     * Get central administrators (all super-admins, and central admins without affiliate).
     */
    protected function getCentralAdminRecipients()
    {
        return User::where(function ($query) {
            $query->whereHas('roles', fn($q) => $q->where('name', 'super-admin'))
                ->orWhere(function ($q) {
                    $q->whereNull('affiliate_id')
                        ->whereHas('roles', fn($r) => $r->where('name', 'admin'));
                });
        })->get();
    }

    /**
     * Notify relevant users when a new booking is created.
     */
    public function notifyNewBooking(Booking $booking): array
    {
        $recipients = collect();

        if ($booking->affiliate_id) {
            $affiliateUsers = User::where('affiliate_id', $booking->affiliate_id)->get();
            $recipients = $recipients->merge($affiliateUsers);
        }

        $recipients = $recipients->merge($this->getCentralAdminRecipients());

        if ($creatorId = auth()->id()) {
            $recipients = $recipients->reject(fn($u) => $u->id === $creatorId);
        }

        $recipients = $recipients->unique('id')->values();

        $title = 'Booking Baru';
        $body = "Booking #{$booking->booking_code} ({$booking->customer_name}) telah dibuat.";
        $data = [
            'type' => 'new_booking',
            'booking_id' => (string) $booking->id,
            'booking_code' => (string) $booking->booking_code,
            'route' => '/booking-detail',
        ];

        return $this->sendToUsers($recipients, $title, $body, $data);
    }

    /**
     * Notify relevant users when a booking payment is recorded/confirmed.
     */
    public function notifyBookingPayment(Booking $booking, string $statusText = 'LUNAS'): array
    {
        $recipients = collect();

        if ($booking->user_id) {
            if ($booking->relationLoaded('user') && $booking->user) {
                $recipients->push($booking->user);
            } else {
                $assignedUser = User::find($booking->user_id);
                if ($assignedUser) {
                    $recipients->push($assignedUser);
                }
            }
        }

        if ($booking->affiliate_id) {
            $affiliateAdmins = User::where('affiliate_id', $booking->affiliate_id)
                ->whereHas('roles', fn($q) => $q->whereIn('name', ['affiliate-admin', 'admin']))
                ->get();
            $recipients = $recipients->merge($affiliateAdmins);
        }

        $recipients = $recipients->merge($this->getCentralAdminRecipients());

        $recipients = $recipients->unique('id')->values();

        $title = 'Pembayaran Booking Diterima';
        $body = "Pembayaran ({$statusText}) untuk booking #{$booking->booking_code} ({$booking->customer_name}) telah dicatat.";
        $data = [
            'type' => 'booking_payment',
            'booking_id' => (string) $booking->id,
            'booking_code' => (string) $booking->booking_code,
            'route' => '/booking-detail',
        ];

        return $this->sendToUsers($recipients, $title, $body, $data);
    }

    /**
     * Notify relevant users when a booking status changes to confirmed.
     */
    public function notifyBookingConfirmed(Booking $booking): array
    {
        $recipients = collect();

        if ($booking->user_id) {
            if ($booking->relationLoaded('user') && $booking->user) {
                $recipients->push($booking->user);
            } else {
                $assignedUser = User::find($booking->user_id);
                if ($assignedUser) {
                    $recipients->push($assignedUser);
                }
            }
        }

        if ($booking->affiliate_id) {
            $affiliateUsers = User::where('affiliate_id', $booking->affiliate_id)->get();
            $recipients = $recipients->merge($affiliateUsers);
        }

        $recipients = $recipients->merge($this->getCentralAdminRecipients());

        $recipients = $recipients->unique('id')->values();

        $title = 'Booking Dikonfirmasi';
        $body = "Booking #{$booking->booking_code} ({$booking->customer_name}) telah dikonfirmasi dan siap untuk serah terima.";
        $data = [
            'type' => 'booking_confirmed',
            'booking_id' => (string) $booking->id,
            'booking_code' => (string) $booking->booking_code,
            'route' => '/booking-detail',
        ];

        return $this->sendToUsers($recipients, $title, $body, $data);
    }

    /**
     * Notify destination affiliate users when an iPhone transfer is created.
     */
    public function notifyIphoneTransfer(IphoneTransfer $transfer): array
    {
        // Only notify users belonging to destination affiliate
        $recipients = User::where('affiliate_id', $transfer->to_affiliate_id)->get();

        if ($senderId = $transfer->sent_by) {
            $recipients = $recipients->reject(fn($u) => (string) $u->id === (string) $senderId);
        }

        $recipients = $recipients->unique('id')->values();

        $unitName = $transfer->iphone?->name ?? 'iPhone';
        $destAffiliateName = $transfer->toAffiliate?->name ?? 'cabang Anda';

        $title = 'Pengiriman Unit iPhone';
        $body = "Unit {$unitName} sedang dalam pengiriman ke cabang {$destAffiliateName}.";
        $data = [
            'type' => 'iphone_transfer',
            'transfer_id' => (string) $transfer->id,
            'iphone_id' => (string) $transfer->iphone_id,
            'to_affiliate_id' => (string) $transfer->to_affiliate_id,
            'route' => '/affiliates/transfers',
        ];

        return $this->sendToUsers($recipients, $title, $body, $data);
    }

    /**
     * Notify relevant users when rental approaches return time.
     */
    public function notifyReturnReminder(Booking $booking): array
    {
        $recipients = collect();

        if ($booking->user_id) {
            if ($booking->relationLoaded('user') && $booking->user) {
                $recipients->push($booking->user);
            } else {
                $assignedUser = User::find($booking->user_id);
                if ($assignedUser) {
                    $recipients->push($assignedUser);
                }
            }
        }

        if ($booking->affiliate_id) {
            $affiliateUsers = User::where('affiliate_id', $booking->affiliate_id)->get();
            $recipients = $recipients->merge($affiliateUsers);
        }

        $recipients = $recipients->merge($this->getCentralAdminRecipients());

        $recipients = $recipients->unique('id')->values();

        $title = 'Pengingat Pengembalian Unit';
        $body = "Sewa unit untuk booking #{$booking->booking_code} ({$booking->customer_name}) mendekati batas waktu pengembalian.";
        $data = [
            'type' => 'return_reminder',
            'booking_id' => (string) $booking->id,
            'booking_code' => (string) $booking->booking_code,
            'route' => '/booking-detail',
        ];

        return $this->sendToUsers($recipients, $title, $body, $data);
    }

    /**
     * Send message using FCM HTTP v1 API.
     */
    protected function sendViaHttpV1(string $token, string $title, string $body, array $data): array
    {
        $projectId = config('services.firebase.project_id', 'skyrental-admin');
        $accessToken = $this->getOAuthAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $formattedData = $this->formatDataPayload($data);

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'high_importance_channel',
                        'notification_priority' => 'PRIORITY_MAX',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                        'visibility' => 'PUBLIC',
                    ],
                ],
            ],
        ];

        if (!empty($formattedData)) {
            $payload['message']['data'] = $formattedData;
        }

        if (!$accessToken) {
            $errorMsg = 'FCM HTTP v1 requires an OAuth 2.0 access token, but credentials could not be loaded. Please check FIREBASE_CREDENTIALS in .env.';
            Log::error($errorMsg);
            return [
                'success' => false,
                'token' => $token,
                'response' => ['error' => $errorMsg],
                'error' => $errorMsg,
            ];
        }

        $request = Http::withHeaders([
            'Content-Type' => 'application/json; UTF-8',
        ])->withToken($accessToken);

        $response = $request->post($url, $payload);

        if ($response->successful()) {
            return [
                'success' => true,
                'token' => $token,
                'response' => $response->json(),
                'error' => null,
            ];
        }

        $statusCode = $response->status();
        $responseBody = $response->json() ?? [];
        $errorMessage = $response->body();

        // Check if token is invalid or unregistered
        if ($this->isV1TokenInvalidOrUnregistered($statusCode, $responseBody, $errorMessage)) {
            $this->removeInvalidToken($token);
        }

        Log::warning('FCM HTTP v1 delivery failed', [
            'token' => $token,
            'status' => $statusCode,
            'error' => $responseBody,
        ]);

        return [
            'success' => false,
            'token' => $token,
            'response' => $responseBody,
            'error' => $errorMessage,
        ];
    }

    /**
     * Send message using FCM Legacy HTTP protocol.
     */
    protected function sendViaLegacy(string $token, string $title, string $body, array $data, string $serverKey): array
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $formattedData = $this->formatDataPayload($data);

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (!empty($formattedData)) {
            $payload['data'] = $formattedData;
        }

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        $responseBody = $response->json() ?? [];

        if ($response->successful() && !empty($responseBody['results'][0]['message_id'])) {
            return [
                'success' => true,
                'token' => $token,
                'response' => $responseBody,
                'error' => null,
            ];
        }

        $error = $responseBody['results'][0]['error'] ?? ($response->successful() ? 'Unknown legacy FCM error' : $response->body());

        if (in_array($error, ['NotRegistered', 'InvalidRegistration', 'MismatchSenderId'], true)) {
            $this->removeInvalidToken($token);
        }

        Log::warning('FCM Legacy delivery failed', [
            'token' => $token,
            'status' => $response->status(),
            'error' => $error,
        ]);

        return [
            'success' => false,
            'token' => $token,
            'response' => $responseBody,
            'error' => (string) $error,
        ];
    }

    /**
     * Remove an invalid or unregistered device token from the database.
     */
    public function removeInvalidToken(string $token): void
    {
        $deleted = DeviceToken::where('token', $token)->delete();
        if ($deleted > 0) {
            Log::info("Removed invalid/unregistered FCM device token: {$token}");
        }
    }

    /**
     * Check if FCM HTTP v1 response indicates an invalid or unregistered token.
     */
    protected function isV1TokenInvalidOrUnregistered(int $statusCode, array $responseBody, string $rawMessage): bool
    {
        if ($statusCode === 404) {
            return true;
        }

        $status = $responseBody['error']['status'] ?? '';
        if ($status === 'NOT_FOUND') {
            return true;
        }

        $details = $responseBody['error']['details'] ?? [];
        foreach ($details as $detail) {
            $errorCode = $detail['errorCode'] ?? '';
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return true;
            }
        }

        $message = strtolower($responseBody['error']['message'] ?? $rawMessage);
        if (str_contains($message, 'unregistered') || str_contains($message, 'not found') || str_contains($message, 'invalid registration token')) {
            return true;
        }

        return false;
    }

    /**
     * Format data payload so all values are stringified as required by FCM.
     */
    protected function formatDataPayload(array $data): array
    {
        $formatted = [];
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $formatted[(string) $key] = '';
            } elseif (is_bool($value)) {
                $formatted[(string) $key] = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $formatted[(string) $key] = (string) $value;
            } else {
                $formatted[(string) $key] = json_encode($value);
            }
        }

        return $formatted;
    }

    /**
     * Obtain or retrieve cached Google OAuth2 Access Token using service account credentials.
     */
    protected function getOAuthAccessToken(): ?string
    {
        if (app()->environment('testing')) {
            return 'mock_test_access_token';
        }

        $cachedToken = Cache::get('fcm_oauth2_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        $credentialsConfig = config('services.firebase.credentials');
        if (!$credentialsConfig) {
            return null;
        }

        $jsonContent = null;
        if (is_string($credentialsConfig)) {
            $candidatePaths = array_unique(array_filter([
                $credentialsConfig,
                base_path($credentialsConfig),
                storage_path('app/' . basename($credentialsConfig)),
                storage_path('app/firebase-credentials.json'),
                app_path(basename($credentialsConfig)),
                base_path('app/' . basename($credentialsConfig)),
            ]));

            foreach ($candidatePaths as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $jsonContent = file_get_contents($path);
                    if ($jsonContent) {
                        break;
                    }
                }
            }

            if (!$jsonContent && str_starts_with(trim($credentialsConfig), '{')) {
                $jsonContent = $credentialsConfig;
            }
        }

        if (!$jsonContent) {
            return null;
        }

        $credentials = json_decode($jsonContent, true);
        if (!isset($credentials['client_email'], $credentials['private_key'])) {
            return null;
        }

        $clientEmail = $credentials['client_email'];
        $privateKey = $credentials['private_key'];

        $now = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $dataToSign = "{$header}.{$payload}";
        $signature = '';

        if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('FCM OAuth RS256 signature failed');
            return null;
        }

        $jwt = "{$dataToSign}." . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);
            $cacheDuration = max(60, $expiresIn - 300);

            Cache::put('fcm_oauth2_access_token', $accessToken, now()->addSeconds($cacheDuration));

            return $accessToken;
        }

        Log::error('Failed to obtain FCM OAuth access token', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return null;
    }

    /**
     * URL-safe Base64 encode without padding.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
