<?php

namespace App\Livewire\Booking;

use App\Models\Booking;
use App\Models\Iphones;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class Create extends Component
{
    public $iphones;
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;
    public $selectedIphone = null;
    public $countryCode = '+62';

    public $selectedPrice = null;

    public $iphone_id;
    public $customer_name;
    public $customer_phone;
    public $customer_email;
    public $selectedIphoneId = null;
    public $requested_booking_date;
    public $end_booking_date;
    public $requested_time;
    public $end_time;
    public $price = 0; // Assuming you have a way to calculate or set this

    public $selectedDuration = null;

    public $durations = [];

    public function save()
    {
        // Validasi input awal
        $this->validate([
            'selectedIphoneId' => 'required|exists:iphones,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:15',
            'customer_email' => 'nullable|email|max:255',
            'requested_booking_date' => 'required|date',
            'requested_time' => 'required|date_format:H:i',
            'selectedDuration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        // 🔄 Hitung end_booking_date dan end_time terlebih dahulu
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

        // Gabungkan tanggal dan waktu
        $start = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($this->requested_booking_date)->format('Y-m-d') . ' ' . $this->requested_time);
        $end = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($this->end_booking_date)->format('Y-m-d') . ' ' . $this->end_time);

        // 🔍 Cek apakah ada booking bentrok
        $bookings = Booking::where('iphone_id', $this->selectedIphoneId)->get();

        $conflict = $bookings->contains(function ($booking) use ($start, $end) {
            $bookingStart = Carbon::parse($booking->requested_booking_date . ' ' . $booking->requested_time);
            $bookingEnd = Carbon::parse($booking->end_booking_date . ' ' . $booking->end_time);

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

        // Simpan booking baru
        Booking::create([
            'iphone_id' => $this->selectedIphoneId,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->countryCode . '-' . $this->customer_phone,
            'customer_email' => $this->customer_email,
            'requested_booking_date' => $this->requested_booking_date->toDateString(),
            'requested_time' => $this->requested_time,
            'end_booking_date' => $end->toDateString(),
            'end_time' => $end->format('H:i'),
            'duration' => $this->selectedDuration,
            'price' => $this->selectedPrice,
            'status' => 'pending',
            'created' => Carbon::now('Asia/Jakarta')
        ]);

        // Reset
        $this->dispatch('close-modal');
        $this->reset([
            'selectedDuration',
            'selectedIphone',
            'selectedIphoneId',
            'customer_name',
            'customer_phone',
            'customer_email',
            'requested_booking_date',
            'end_booking_date',
            'requested_time',
            'end_time',
            'selectedDuration',
            'price'
        ]);

        LivewireAlert::title('Success!')
            ->text('Booking berhasil disimpan.')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }




    public function getDuration()
    {
        if ($this->selectedIphoneId) {
            // dd('Selected iPhone ID:', $this->selectedIphoneId);
            $iphone = Iphones::with('durations')->find($this->selectedIphoneId);
            $this->durations = $iphone->durations->map(function ($duration, $index) {
                return [
                    'index' => $index,
                    'hours' => $duration->hours,           // dari tabel durations
                    'price' => (int) $duration->pivot->price, // dari pivot table
                ];
            })->toArray();
            // dd($this->durations);
        }
    }

    public function calculateEndDateTime()
    {
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

    public function mount()
    {
        $this->requested_booking_date = Carbon::now('Asia/Jakarta');
        $this->requested_time = Carbon::now('Asia/Jakarta')->format('H:i');
    }

    public function getData()
    {
        return Iphones::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })->orderBy($this->sortField, $this->sortDirection)->get();
    }

    public function render()
    {
        return view('livewire.booking.create', [
            'iphonesQuery' => $this->getData()
        ]);
    }
}
