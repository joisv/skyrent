<?php

namespace Tests\Feature;

use App\Livewire\RentIphoneWizard;
use App\Models\Booking;
use App\Models\Gallery;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentIphoneWizardAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booked_iphone_is_disabled_and_unavailable_for_overlapping_period(): void
    {
        $gallery = Gallery::create(['image' => 'test.jpg']);
        $user = User::factory()->create();
        $this->actingAs($user);

        Payment::create([
            'name' => 'BCA Transfer',
            'slug' => 'bca-transfer',
            'description' => 'Transfer ke rekening BCA',
        ]);

        // Physical Unit A: MGHRYH75VK
        $unitA = Iphones::create([
            'name' => 'iPhone 13 Pink',
            'slug' => 'iphone-13-pink-1',
            'serial_number' => 'MGHRYH75VK',
            'asset_code' => 'AST-IP13-001',
            'gallery_id' => $gallery->id,
            'user_id' => $user->id,
            'status' => 'ready',
        ]);

        // Physical Unit B: Same model name, different physical unit
        $unitB = Iphones::create([
            'name' => 'iPhone 13 Pink',
            'slug' => 'iphone-13-pink-2',
            'serial_number' => 'MQJX04QXV1',
            'asset_code' => 'AST-IP13-002',
            'gallery_id' => $gallery->id,
            'user_id' => $user->id,
            'status' => 'ready',
        ]);

        // Ensure Unit A has a confirmed booking for 2026-10-05 10:02 to 2026-10-06 10:02
        $booking = Booking::create([
            'iphone_id' => $unitA->id,
            'customer_name' => 'Tester',
            'customer_phone' => '081234567890',
            'requested_booking_date' => '2026-10-05',
            'requested_time' => '10:02:00',
            'start_booking_date' => '2026-10-05',
            'start_time' => '10:02:00',
            'end_booking_date' => '2026-10-06',
            'end_time' => '10:02:00',
            'duration' => 24,
            'status' => 'confirmed',
            'price' => 100000,
            'created' => now(),
            'booking_code' => Booking::generateBookingCode(),
        ]);

        // Test Livewire component
        $component = Livewire::test(RentIphoneWizard::class)
            ->set('requested_booking_date', '2026-10-05')
            ->set('requested_time', '10:15')
            ->set('selectedDuration', 24)
            ->set('iphone_search', 'iPhone 13 Pink');

        $iphones = $component->get('iphones');
        $loadedUnitA = $iphones->firstWhere('serial_number', 'MGHRYH75VK');
        $loadedUnitB = $iphones->firstWhere('serial_number', 'MQJX04QXV1');

        $this->assertNotNull($loadedUnitA);
        $this->assertFalse($loadedUnitA->is_available, 'Unit A (MGHRYH75VK) must be unavailable during overlapping period');

        $this->assertNotNull($loadedUnitB);
        $this->assertTrue($loadedUnitB->is_available, 'Unit B (MQJX04QXV1) must remain available despite same model name');

        // Test server-side guard on selectIphone
        $component->call('selectIphone', $unitA->id, $unitA->name, $unitA->serial_number);
        $this->assertNotEquals($unitA->id, $component->get('selectedIphoneId'), 'selectIphone must not select unavailable unit A');

        $component->call('selectIphone', $unitB->id, $unitB->name, $unitB->serial_number);
        $this->assertEquals($unitB->id, $component->get('selectedIphoneId'), 'selectIphone must allow available unit B');

        // Test non-overlapping future period
        $component->set('requested_booking_date', '2026-10-07')
            ->set('requested_time', '10:00')
            ->set('selectedDuration', 24)
            ->call('loadIphones');

        $futureIphones = $component->get('iphones');
        $futureUnitA = $futureIphones->firstWhere('serial_number', 'MGHRYH75VK');
        $this->assertTrue($futureUnitA->is_available, 'Unit A (MGHRYH75VK) must be available for non-overlapping future period');

        // Test server-side guard on submit() preventing overlapping booking
        $component->set('selectedIphoneId', $unitA->id)
            ->set('requested_booking_date', '2026-10-05')
            ->set('requested_time', '10:15')
            ->set('selectedDuration', 24)
            ->set('customer_name', 'Race User')
            ->set('customer_phone', '8123-4567-8901')
            ->set('customer_email', 'race@example.com')
            ->set('address', 'Jl. Test 123')
            ->set('price', 100000)
            ->set('selectedPrice', 100000);

        $countBefore = Booking::where('iphone_id', $unitA->id)->count();
        $component->call('submit');
        $countAfter = Booking::where('iphone_id', $unitA->id)->count();
        $this->assertEquals($countBefore, $countAfter, 'submit() must reject booking on unavailable physical unit');
    }
}
