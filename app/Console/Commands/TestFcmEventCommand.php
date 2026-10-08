<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

class TestFcmEventCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-event 
                            {event : Event to test (new_booking, booking_payment, booking_confirmed, iphone_transfer, return_reminder)}
                            {token? : Target FCM device token OR user ID / email OR role (super-admin, affiliate-admin) (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test push notification for a specific SkyRental event to all active devices of target user or role';

    /**
     * Execute the console command.
     */
    public function handle(FcmService $fcmService): int
    {
        $event = $this->argument('event');
        $target = $this->argument('token');

        $events = [
            'new_booking' => [
                'title' => 'Booking Baru',
                'body' => 'Booking #SKY261007TEST (Budi Santoso) telah dibuat.',
                'data' => [
                    'type' => 'new_booking',
                    'booking_id' => '1',
                    'booking_code' => 'SKY261007TEST',
                    'route' => '/booking-detail',
                ],
            ],
            'booking_payment' => [
                'title' => 'Pembayaran Booking Diterima',
                'body' => 'Pembayaran (LUNAS) untuk booking #SKY261007TEST (Budi Santoso) telah dicatat.',
                'data' => [
                    'type' => 'booking_payment',
                    'booking_id' => '1',
                    'booking_code' => 'SKY261007TEST',
                    'route' => '/booking-detail',
                ],
            ],
            'booking_confirmed' => [
                'title' => 'Booking Dikonfirmasi',
                'body' => 'Booking #SKY261007TEST (Budi Santoso) telah dikonfirmasi dan siap untuk serah terima.',
                'data' => [
                    'type' => 'booking_confirmed',
                    'booking_id' => '1',
                    'booking_code' => 'SKY261007TEST',
                    'route' => '/booking-detail',
                ],
            ],
            'iphone_transfer' => [
                'title' => 'Pengiriman Unit iPhone',
                'body' => 'Unit iPhone 15 Pro sedang dalam pengiriman ke cabang Solo.',
                'data' => [
                    'type' => 'iphone_transfer',
                    'transfer_id' => '1',
                    'iphone_id' => '1',
                    'to_affiliate_id' => '2',
                    'route' => '/affiliates/transfers',
                ],
            ],
            'return_reminder' => [
                'title' => 'Pengingat Pengembalian Unit',
                'body' => 'Sewa unit untuk booking #SKY261007TEST (Budi Santoso) mendekati batas waktu pengembalian.',
                'data' => [
                    'type' => 'return_reminder',
                    'booking_id' => '1',
                    'booking_code' => 'SKY261007TEST',
                    'route' => '/booking-detail',
                ],
            ],
        ];

        if (!isset($events[$event])) {
            $this->error("Unknown event '{$event}'. Supported events: " . implode(', ', array_keys($events)));
            return 1;
        }

        $payload = $events[$event];
        $tokens = [];
        $tokenIds = [];
        $targetUser = null;
        $standaloneToken = null;

        if (empty($target)) {
            // Find target user from latest registered device token
            $latestToken = DeviceToken::latest('updated_at')->first();
            if (!$latestToken) {
                $this->error('No device token found in database. Please log in from the Flutter app or provide a token/user argument.');
                return 1;
            }

            $targetUser = User::with('roles', 'affiliate')->find($latestToken->user_id);
        } elseif (\Spatie\Permission\Models\Role::where('name', $target)->exists()) {
            // Target is a role name (e.g. super-admin, affiliate-admin)
            $targetUser = User::with('roles', 'affiliate')
                ->role($target)
                ->whereHas('deviceTokens')
                ->first();

            if (!$targetUser) {
                $targetUser = User::with('roles', 'affiliate')->role($target)->first();
            }

            if (!$targetUser) {
                $this->error("No user found with role '{$target}'.");
                return 1;
            }
        } elseif ($user = User::with('roles', 'affiliate')->where('id', $target)->orWhere('email', $target)->first()) {
            $targetUser = $user;
        } elseif ($deviceToken = DeviceToken::where('token', $target)->first()) {
            $targetUser = User::with('roles', 'affiliate')->find($deviceToken->user_id);
            if (!$targetUser) {
                $standaloneToken = $target;
                $tokenIds = [$deviceToken->id];
            }
        } else {
            // Standalone token string (e.g. from unit tests)
            $standaloneToken = $target;
            $tokenIds = ['N/A'];
        }

        if ($targetUser) {
            $tokenRecords = DeviceToken::where('user_id', $targetUser->id)->get();
            $tokens = $tokenRecords->pluck('token')->filter()->unique()->values()->all();
            $tokenIds = $tokenRecords->pluck('id')->all();
        } elseif ($standaloneToken) {
            $tokens = [$standaloneToken];
        }

        // Diagnostic information formatting
        if ($targetUser) {
            $roles = $targetUser->roles->pluck('name')->all();
            $roleName = !empty($roles) ? implode(', ', $roles) : 'none';
            $affiliateName = $targetUser->affiliate?->name;
            $affiliateInfo = $targetUser->affiliate_id 
                ? "ID {$targetUser->affiliate_id}" . ($affiliateName ? " ({$affiliateName})" : "")
                : "Global Access (NULL)";

            if (in_array('super-admin', $roles)) {
                $recipientQuery = "Central / Global Scope: User::role('super-admin') & User::whereNull('affiliate_id')->role('admin') (always included across all affiliate bookings)";
            } elseif ($targetUser->affiliate_id) {
                $recipientQuery = "Affiliate Scoped: User::where('affiliate_id', {$targetUser->affiliate_id})->role('{$roleName}') (matched for {$affiliateInfo} bookings)";
            } else {
                $recipientQuery = "Role Scoped: User::role('{$roleName}')";
            }
            $targetDesc = "user {$targetUser->email} ({$targetUser->id})";
        } else {
            $roleName = 'N/A';
            $affiliateInfo = 'N/A';
            $recipientQuery = 'Direct Device Token Dispatch (Bypasses user role query)';
            $targetDesc = 'standalone token ' . substr($standaloneToken ?? '', 0, 20) . '...';
        }

        $this->line('');
        $this->info('================================================================');
        $this->info('                   FCM EVENT TEST DIAGNOSTICS                   ');
        $this->info('================================================================');
        $this->line("Event:                      {$event}");
        $this->line("Target user:                " . ($targetUser ? "{$targetUser->email} ({$targetUser->id})" : "Explicit Token / Anonymous"));
        $this->line("Role:                       {$roleName}");
        $this->line("Affiliate ID:               {$affiliateInfo}");
        $this->line("Active token count:         " . count($tokens));
        $this->line("Token IDs:                  [" . implode(', ', $tokenIds) . "]");
        $this->line("Notification recipient query: {$recipientQuery}");
        $this->info('================================================================');
        $this->line('');

        if (empty($tokens)) {
            $this->error("No active device tokens found for {$targetDesc}.");
            return 1;
        }

        $count = count($tokens);
        $this->info("Dispatching test '{$event}' notification to {$count} device token(s) for {$targetDesc}...");

        $results = $fcmService->sendToTokens($tokens, $payload['title'], $payload['body'], $payload['data']);

        $successCount = $results['success_count'];
        $failureCount = $results['failure_count'];

        $this->info("Notification results for '{$event}': {$successCount}/{$count} delivered successfully.");

        foreach ($results['results'] as $idx => $res) {
            $deviceNum = $idx + 1;
            $tokenSub = substr($res['token'], 0, 25) . '...';
            $tokenId = $tokenIds[$idx] ?? 'N/A';
            if ($res['success']) {
                $msgId = $res['response']['name'] ?? $res['response']['results'][0]['message_id'] ?? 'DELIVERED';
                $this->line("  ✓ Device {$deviceNum} (Token ID {$tokenId}) [{$tokenSub}]: Delivered ({$msgId})");
            } else {
                $err = $res['error'] ?? 'Unknown error';
                $this->warn("  ✗ Device {$deviceNum} (Token ID {$tokenId}) [{$tokenSub}]: Failed ({$err})");
            }
        }

        return $successCount > 0 ? 0 : 1;
    }
}
