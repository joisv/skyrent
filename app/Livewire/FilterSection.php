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
        // Waktu yang diminta user
        $requestedStart = Carbon::parse($this->selectedDate)
            ->timezone('Asia/Jakarta')
            ->setTime((int)$this->selectedHour, (int)$this->selectedMinute)
            ->format('Y-m-d H:i:s');

        // Ambil semua ID iPhone yang sedang dibooking pada tanggal tertentu
        $this->iphoneByDate = Iphones::with('durations')
            ->whereDoesntHave('bookings', function ($query) use ($requestedStart) {
                $query->whereIn('status', ['pending', 'confirmed'])
                    ->whereRaw("datetime(requested_booking_date || ' ' || requested_time) <= ?", [$requestedStart])
                    ->whereRaw("datetime(requested_booking_date || ' ' || requested_time, '+' || duration || ' hours') >= ?", [$requestedStart]);
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
