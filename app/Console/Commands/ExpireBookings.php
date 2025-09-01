<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $compareTime = now()->subMinutes(1);

        $this->info("Compare time: " . $compareTime);

        $expiredBookings = Booking::where('status', 'pending')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(requested_booking_date, ' ', requested_time), '%Y-%m-%d %H:%i:%s') < ?",
                [$compareTime]
            )
            ->get();

        dd($expiredBookings);
    }
}
