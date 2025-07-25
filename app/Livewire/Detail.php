<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class Detail extends Component
{
    public $iphone;
    public $selectedPrice;
    public $selectedDateFormatted; // 25 Juli 2025, 20:51
    public $selectedDuration; // 24
    public $selectedHour; // 20
    public $selectedMinute; //
    public $is_available = false;
    public $selectedIphoneId;
    public $selectedDate; // Date object for the selected date
    public $customer_name;
    public $customer_phone;
    public $customer_email;
    public $countryCode = '+62';

    #[On('updated:selectedIphoneId')]
    #[On('updated:selectedDate')]
    #[On('updated:selectedHour')]
    #[On('updated:selectedMinute')]
    #[On('updated:selectedDuration')]
    public function updated()
    {
        $this->checkAvailability();
        // dd([$this->is_available, $this->selectedDate]);
    }

    public function checkAvailability()
    {
        try {
            $start = Carbon::parse($this->selectedDate)->setTimezone('Asia/Jakarta');
            $end = $start->copy()->addHours($this->selectedDuration);
        } catch (\Exception $e) {
            $this->is_available = false;
            return;
        }

        $bookings = Booking::where('iphone_id', $this->selectedIphoneId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $this->is_available = true;

        foreach ($bookings as $booking) {
            $bookingStart = Carbon::parse("{$booking->requested_booking_date} {$booking->requested_time}", 'Asia/Jakarta');
            $bookingEnd = $bookingStart->copy()->addHours($booking->duration);

            if ($start->lt($bookingEnd) && $end->gt($bookingStart)) {
                $this->is_available = false;
                break;
            }
        }
    }

    public function bookingNow()
    {
        // if (!$this->is_available) {
        //      LivewireAlert::title('Booking Tidak Tersedia')
        //         ->text('Tanggal dan waktu yang dipilih tidak tersedia.')
        //         ->error()
        //         ->toast()
        //         ->position('top-end')
        //         ->show();
        // }
        // $this->validate([
        //     'selectedIphoneId' => 'required|exists:iphones,id',
        //     'selectedDate' => 'required|date',
        //     'selectedHour' => 'required|min:0|max:23',
        //     'selectedMinute' => 'required|min:0|max:59',
        //     'selectedDuration' => 'required|integer|min:1',
        // ]);
        if (collect([
            $this->selectedDuration,
            $this->selectedHour,
            $this->selectedMinute,
            $this->selectedIphoneId,
            $this->selectedDate,
        ])->contains(null)) {
            LivewireAlert::title('Booking Tidak Tersedia')
                ->text('Tanggal dan waktu yang dipilih tidak tersedia.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        $this->dispatch('open-modal', 'user-booking-create');
    }

    public function mount(Iphones $iphone)
    {
        $this->iphone = $iphone;

        $now = Carbon::now('Asia/Jakarta');
        $this->selectedHour = $now->format('H');
        $this->selectedMinute = $now->format('i');
        $this->selectedIphoneId = $iphone->id;
        $this->selectedDuration = $iphone->durations->first()->hours ?? 1; // Default to first duration or 1 hour
        $this->selectedPrice = $iphone->durations->first()->pivot->price ?? 0; // Default to first duration price or 0
    }

    public function render()
    {
        return view('livewire.detail');
    }
}
