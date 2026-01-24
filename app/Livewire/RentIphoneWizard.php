<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class RentIphoneWizard extends Component
{
    public int $step = 1;

    // STEP 1
    public ?int $selectedIphoneId = null;
    public $requested_booking_date;
    public $requested_time;
    public $selectedDuration;
    public $selectedPrice;
    public $durations = [];
    public $end_booking_date;
    public $end_time;

    // STEP 2
    public ?int $rentHours = null;

    // STEP 3
    public string $customerName = '';
    public string $customerPhone = '';

    #[On('iphone-selected')]
    public function setIphone(int $iphoneId)
    {
        $this->selectedIphoneId = $iphoneId;
        $this->getDurations();
        $this->dispatch('display-duration-options');
    }

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'selectedIphoneId' => 'required|exists:iphones,id',
            ],
            2 => [
                'rentHours' => 'required|integer|min:1',
            ],
            3 => [
                'customerName' => 'required|min:3',
                'customerPhone' => 'required',
            ],
            default => [],
        };
    }

    public function next(): void
    {
        $this->validate();

        if ($this->step === 1) {
            $this->handleStepOne(); // kalau lolos → lanjut
        }

        if ($this->step < 4) {
            $this->step++;
        }
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function handleStepOne()
    {
        $this->calculateEndDateTime();

        if (!$this->end_booking_date || !$this->end_time) {
            LivewireAlert::title('Gagal Hitung Waktu')
                ->text('Gagal menghitung waktu selesai booking.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        // Gabungkan tanggal dan waktu dari booking sekarang
        $start = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($this->requested_booking_date)->format('Y-m-d') . ' ' . $this->requested_time);
        $end = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($this->end_booking_date)->format('Y-m-d') . ' ' . $this->end_time);

        // 🔍 Cek apakah ada booking bentrok
        $bookings = Booking::where('iphone_id', $this->selectedIphoneId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $conflict = $bookings->contains(function ($booking) use ($start, $end) {
            $bookingStart = Carbon::parse($booking->requested_booking_date . ' ' . $booking->requested_time);

            // Hitung bookingEnd berdasarkan requested + durasi
            $bookingEnd = $bookingStart->copy()->addHours((int) $booking->duration);

            // Cek apakah waktu booking lama bertabrakan dengan booking baru
            return $bookingStart < $end && $bookingEnd > $start;
        });

        if ($conflict) {
            LivewireAlert::title('Booking Gagal')
                ->text('Tanggal dan waktu yang dipilih sudah dibooking.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }
    }

    public function calculateEndDateTime()
    {
        dd([
            'requested_booking_date' => $this->requested_booking_date,
            'requested_time' => $this->requested_time,
            'end_booking_date' => $this->end_booking_date,
            'end_time' => $this->end_time,
            'selectedDuration' => $this->selectedDuration,
        ]);
        if (!$this->requested_booking_date || !$this->requested_time || !$this->selectedDuration) {
            $this->end_booking_date = null;
            $this->end_time = null;
            return;
        }

        try {
            $dateOnly = Carbon::parse($this->requested_booking_date)->toDateString(); // pastikan hanya tanggal
            $startDateTime = Carbon::parse("{$dateOnly} {$this->requested_time}");

            $endDateTime = $startDateTime->copy()->addHours($this->selectedDuration);

            $this->end_booking_date = $endDateTime->toDateString();
            $this->end_time = $endDateTime->format('H:i');
        } catch (\Exception $e) {
            logger()->error('DateTime parse error: ' . $e->getMessage());
            $this->end_booking_date = null;
            $this->end_time = null;
        }
    }

    public function getDurations()
    {
        if ($this->selectedIphoneId) {
            $iphone = Iphones::with('durations')->find($this->selectedIphoneId);
            $this->durations = $iphone->durations->map(function ($duration, $index) {
                return [
                    'index' => $index,
                    'hours' => $duration->hours,           // dari tabel durations
                    'price' => (int) $duration->pivot->price, // dari pivot table
                ];
            })->toArray();
        }
    }

    public function submit(): void
    {
        $this->validate();

        // Rental::create([...]);

        session()->flash('success', 'Sewa berhasil dibuat');
    }

    // #[On('booking-time-set')]
    // public function receiveBookingTime(string $date, string $time): void
    // {
    //     $this->requested_booking_date = $date;
    //     $this->requested_time = $time;
    // }

    public function render()
    {
        return view('livewire.rent-iphone-wizard');
    }
}
