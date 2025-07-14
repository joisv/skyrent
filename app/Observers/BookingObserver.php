<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Revenue;
use Carbon\Carbon;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        // Cek apakah status berubah ke 'completed'
        if ($booking->isDirty('status') && $booking->status === 'confirmed') {
            // Hindari duplikasi revenue
            if (!$booking->revenue) {
                Revenue::create([
                    'booking_id' => $booking->id,
                    'amount' => $booking->price,
                    'created' => Carbon::now('Asia/Jakarta')
                ]);
            }
        }

        // Cek apakah status berubah ke 'cancelled'
        if ($booking->isDirty('status') && $booking->status === 'cancelled') {
            if ($booking->revenue) {
                $booking->revenue->delete();
            }
        }
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "restored" event.
     */
    public function restored(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "force deleted" event.
     */
    public function forceDeleted(Booking $booking): void
    {
        //
    }
}
