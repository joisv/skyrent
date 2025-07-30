<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class FilterSection extends Component
{
    public $selectedDateFormatted = '';
    public $iphoneByDate;
    public $selectedHour;
    public $selectedMinute;
    public $selectedDate;

    public function getIphoneByDate()
    {
        $date = Carbon::parse($this->selectedDate)->toDateString();

        // Ambil semua ID iPhone yang sedang dibooking pada tanggal tertentu
        $this->iphoneByDate = Iphones::with('durations')
            ->whereDoesntHave('bookings', function ($query) use ($date) {
                $query->where(function ($sub) use ($date) {
                    $sub->where('status', 'pending')
                        ->whereDate('requested_booking_date', $date);
                })->orWhere(function ($sub) use ($date) {
                    $sub->where('status', 'confirmed')
                        ->whereDate('start_booking_date', '<=', $date)
                        ->whereDate('end_booking_date', '>=', $date);
                });
            })
            ->get();
    }

    public function mount()
    {
        $now = Carbon::now('Asia/Jakarta');
        $this->selectedHour = $now->format('H');
        $this->selectedMinute = $now->format('i');
    }

    public function render()
    {
        return view('livewire.filter-section');
    }
}
