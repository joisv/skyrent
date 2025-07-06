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
    public $start_booking_date;
    public $end_booking_date;
    public $start_time;
    public $end_time;
    public $price = 0; // Assuming you have a way to calculate or set this

    public $selectedDuration = null;

    public $durations = [];

    public function save()
    {
        if (!empty($this->start_booking_date) && !empty($this->start_time) && !empty($this->selectedDuration)) {
            $this->calculateEndDateTime();
        }

        $this->validate([
            'selectedIphoneId' => 'required|exists:iphones,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:15',
            'customer_email' => 'nullable|email|max:255',
            'start_booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_booking_date' => 'required|date',
            'end_time' => 'required|date_format:H:i',
            'selectedDuration' => 'required|integer|min:1', // Assuming
            'price' => 'required|numeric|min:0',
        ]);

        Booking::create([
            'iphone_id' => $this->selectedIphoneId,
            'customer_name' => $this->customer_name, // Replace with actual customer name
            'customer_phone' => $this->countryCode.'-'.$this->customer_phone, // Replace with actual customer phone
            'customer_email' => $this->customer_email, // Replace with actual customer email
            'start_booking_date' => Carbon::parse($this->start_booking_date)->toDateString(),
            'start_time' => $this->start_time,
            'end_booking_date' => Carbon::parse($this->end_booking_date)->toDateString(),
            'end_time' => $this->end_time,
            'duration' => $this->selectedDuration,
            'price' => $this->selectedPrice, // Assuming you have a way to calculate or set this
            'status' => 'pending', // Default status, can be changed later

        ]);

        // Logic to save the booking
        // For example, you might create a new Booking model instance here
        // and save it to the database.
        $this->dispatch('close-modal');
        $this->reset([
            'selectedIphoneId',
            'customer_name',
            'customer_phone',
            'customer_email',
            'start_booking_date',
            'end_booking_date',
            'start_time',
            'end_time',
            'selectedDuration',
            'price'
        ]);
        LivewireAlert::title('success')
            ->text('Booking created successfully!')
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
        if (!$this->start_booking_date || !$this->start_time || !$this->selectedDuration) {
            $this->end_booking_date = null;
            $this->end_time = null;
            return;
        }

        try {
            $dateOnly = Carbon::parse($this->start_booking_date)->toDateString(); // pastikan hanya tanggal
            $startDateTime = Carbon::parse("{$dateOnly} {$this->start_time}");

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
        $this->start_booking_date = Carbon::now('Asia/Jakarta');
        $this->start_time = Carbon::now('Asia/Jakarta')->format('H:i');
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
