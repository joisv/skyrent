<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\Iphones;
use App\Models\IphoneTransfer;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FcmEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Affiliate $affiliateA;
    protected Affiliate $affiliateB;
    protected User $superAdmin;
    protected User $staffA;
    protected User $staffB;
    protected Iphones $iphone;
    protected Payment $cashPayment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'affiliate-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->affiliateA = Affiliate::create([
            'name' => 'Cabang Solo',
            'code' => 'SOLO',
            'slug' => 'cabang-solo',
            'is_active' => true,
        ]);

        $this->affiliateB = Affiliate::create([
            'name' => 'Cabang Semarang',
            'code' => 'SMG',
            'slug' => 'cabang-semarang',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Pusat',
            'affiliate_id' => null,
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->staffA = User::factory()->create([
            'name' => 'Staff Solo',
            'affiliate_id' => $this->affiliateA->id,
        ]);
        $this->staffA->assignRole('staff');

        $this->staffB = User::factory()->create([
            'name' => 'Staff Semarang',
            'affiliate_id' => $this->affiliateB->id,
        ]);
        $this->staffB->assignRole('staff');

        $this->iphone = Iphones::factory()->create([
            'name' => 'iPhone 15 Pro Max',
            'serial_number' => 'IPH-SN-123',
            'status' => 'ready',
        ]);

        $this->cashPayment = Payment::firstOrCreate(
            ['slug' => 'cash'],
            ['name' => 'Tunai (Cash)', 'is_active' => true]
        );
    }

    public function test_new_booking_dispatches_fcm_notification_to_target_affiliate_and_admins()
    {
        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'fcm-token-superadmin',
        ]);

        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'fcm-token-staff-solo',
        ]);

        DeviceToken::create([
            'user_id' => $this->staffB->id,
            'token' => 'fcm-token-staff-semarang',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
        ]);

        // Create booking for Affiliate A
        $booking = Booking::factory()->create([
            'booking_code' => 'SKY-SOLO-001',
            'customer_name' => 'Rina Wijaya',
            'customer_phone' => '08123456789',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->affiliateA->id,
            'user_id' => $this->staffA->id,
            'status' => 'pending',
            'duration' => 24,
            'price' => 250000,
        ]);

        // Should notify Staff A and SuperAdmin, but NOT Staff B (Semarang)
        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-solo'
                && ($data['data']['type'] ?? '') === 'new_booking'
                && ($data['data']['booking_code'] ?? '') === 'SKY-SOLO-001'
                && ($data['data']['route'] ?? '') === '/booking-detail';
        });

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-superadmin'
                && ($data['data']['type'] ?? '') === 'new_booking';
        });

        Http::assertNotSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-semarang';
        });
    }

    public function test_booking_status_confirmed_dispatches_fcm_notification()
    {
        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'fcm-token-staff-solo',
        ]);

        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'fcm-token-superadmin',
        ]);

        $booking = Booking::factory()->create([
            'booking_code' => 'SKY-CONFIRM-002',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '08123456789',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->affiliateA->id,
            'user_id' => $this->staffA->id,
            'status' => 'pending',
            'duration' => 24,
            'price' => 300000,
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
        ]);

        $booking->status = 'confirmed';
        $booking->save();

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-solo'
                && ($data['data']['type'] ?? '') === 'booking_confirmed'
                && ($data['data']['booking_code'] ?? '') === 'SKY-CONFIRM-002'
                && ($data['data']['route'] ?? '') === '/booking-detail';
        });

        // Super-admin must also receive booking confirmed notification even for affiliate booking
        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-superadmin'
                && ($data['data']['type'] ?? '') === 'booking_confirmed';
        });
    }

    public function test_booking_payment_dispatches_fcm_notification()
    {
        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'fcm-token-staff-solo',
        ]);

        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'fcm-token-superadmin',
        ]);

        $booking = Booking::factory()->create([
            'booking_code' => 'SKY-PAY-003',
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '08123456789',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->affiliateA->id,
            'user_id' => $this->staffA->id,
            'status' => 'pending',
            'duration' => 24,
            'price' => 200000,
            'payment_status' => 'unpaid',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
            'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'payment_id' => $this->cashPayment->id,
            'amount' => 200000,
            'pay' => 200000,
            'send_whatsapp' => false,
        ]);

        $response->assertStatus(201);

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-solo'
                && ($data['data']['type'] ?? '') === 'booking_payment'
                && ($data['data']['booking_code'] ?? '') === 'SKY-PAY-003'
                && ($data['data']['route'] ?? '') === '/booking-detail';
        });

        // Super-admin must also receive payment notification
        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-superadmin'
                && ($data['data']['type'] ?? '') === 'booking_payment';
        });
    }

    public function test_iphone_transfer_only_notifies_destination_affiliate()
    {
        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'fcm-token-staff-solo-origin',
        ]);

        DeviceToken::create([
            'user_id' => $this->staffB->id,
            'token' => 'fcm-token-staff-semarang-dest',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
        ]);

        $transfer = IphoneTransfer::create([
            'iphone_id' => $this->iphone->id,
            'from_affiliate_id' => $this->affiliateA->id,
            'to_affiliate_id' => $this->affiliateB->id,
            'sent_by' => $this->staffA->id,
            'status' => 'in_transit',
            'sent_at' => now(),
        ]);

        // Destination affiliate (Semarang) must receive notification
        Http::assertSent(function (Request $request) use ($transfer) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-semarang-dest'
                && ($data['data']['type'] ?? '') === 'iphone_transfer'
                && ($data['data']['transfer_id'] ?? '') === (string) $transfer->id
                && ($data['data']['to_affiliate_id'] ?? '') === (string) $this->affiliateB->id
                && ($data['data']['route'] ?? '') === '/affiliates/transfers';
        });

        // Origin affiliate (Solo) must NOT receive notification
        Http::assertNotSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-solo-origin';
        });
    }

    public function test_return_reminder_command_dispatches_fcm_notification()
    {
        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'fcm-token-staff-solo',
        ]);

        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'fcm-token-superadmin',
        ]);

        $now = Carbon::now('Asia/Jakarta');
        $booking = Booking::factory()->create([
            'booking_code' => 'SKY-REMIND-999',
            'customer_name' => 'Agus Prayitno',
            'customer_phone' => '08123456789',
            'iphone_id' => $this->iphone->id,
            'affiliate_id' => $this->affiliateA->id,
            'user_id' => $this->staffA->id,
            'status' => 'confirmed',
            'start_booking_date' => $now->toDateString(),
            'start_time' => $now->format('H:i:s'),
            'end_booking_date' => $now->toDateString(),
            'end_time' => $now->copy()->addMinutes(15)->format('H:i:s'),
            'duration' => 24,
            'price' => 200000,
            'reminder_sent' => false,
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            'https://api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $this->artisan('bookings:notify-return')->assertSuccessful();

        $booking->refresh();
        $this->assertTrue((bool) $booking->reminder_sent);

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-staff-solo'
                && ($data['data']['type'] ?? '') === 'return_reminder'
                && ($data['data']['booking_code'] ?? '') === 'SKY-REMIND-999'
                && ($data['data']['route'] ?? '') === '/booking-detail';
        });

        // Super-admin must also receive return reminder notification
        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'fcm-token-superadmin'
                && ($data['data']['type'] ?? '') === 'return_reminder';
        });
    }

    public function test_artisan_fcm_test_event_command()
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'ok'], 200),
        ]);

        $this->artisan('fcm:test-event', [
            'event' => 'iphone_transfer',
            'token' => 'test-device-token-xyz',
        ])->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'test-device-token-xyz'
                && ($data['data']['type'] ?? '') === 'iphone_transfer'
                && ($data['data']['route'] ?? '') === '/affiliates/transfers';
        });
    }

    public function test_artisan_fcm_test_event_command_sends_to_all_devices_of_target_user()
    {
        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'token-device-1-superadmin',
            'platform' => 'android',
            'device_name' => 'Xiaomi Tablet',
        ]);

        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'token-device-2-superadmin',
            'platform' => 'android',
            'device_name' => 'Xiaomi Phone',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'projects/test/messages/123'], 200),
        ]);

        // Calling without token argument must detect target user and dispatch to BOTH devices
        $this->artisan('fcm:test-event', [
            'event' => 'booking_confirmed',
        ])->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'token-device-1-superadmin'
                && ($data['data']['type'] ?? '') === 'booking_confirmed';
        });

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'token-device-2-superadmin'
                && ($data['data']['type'] ?? '') === 'booking_confirmed';
        });
    }

    public function test_artisan_fcm_test_event_command_targets_by_role()
    {
        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'superadmin-target-token-1',
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'token' => 'superadmin-target-token-2',
            'platform' => 'android',
        ]);

        DeviceToken::create([
            'user_id' => $this->staffA->id,
            'token' => 'staff-token-ignored',
            'platform' => 'android',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'projects/test/messages/999'], 200),
        ]);

        $this->artisan('fcm:test-event', [
            'event' => 'booking_payment',
            'token' => 'super-admin',
        ])->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'superadmin-target-token-1'
                && ($data['data']['type'] ?? '') === 'booking_payment';
        });

        Http::assertSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'superadmin-target-token-2'
                && ($data['data']['type'] ?? '') === 'booking_payment';
        });

        Http::assertNotSent(function (Request $request) {
            $data = $request->data()['message'] ?? [];
            return ($data['token'] ?? '') === 'staff-token-ignored';
        });
    }
}

