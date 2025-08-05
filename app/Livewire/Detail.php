<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class Detail extends Component
{
    public $iphone;
    public $selectedPrice;
    public $selectedDateFormatted; // 25 Juli 2025, 20:51
    public $selectedDate; // Date object for the selected date
    public $selectedDuration; // 24
    public $selectedHour; // 20
    public $selectedMinute; // 10
    public $is_available = false;
    public $selectedIphoneId;
    public $customer_name;
    public $customer_phone;
    public $customer_email;
    public $countryCode = '+62';

    public $payments;
    public $selectedPaymentId = 1;
    public $selectedPayment;

    public $rating = 0;
    public $name;

    #[On('updated:selectedIphoneId')]
    #[On('updated:selectedDate')]
    #[On('updated:selectedHour')]
    #[On('updated:selectedMinute')]
    #[On('updated:selectedDuration')]
    public function updated()
    {
        $this->checkAvailability();
    }

    public function updatedSelectedPaymentId()
    {
        $this->selectedPayment = $this->payments->firstWhere('id', $this->selectedPaymentId);
        if (!$this->selectedPayment) {
            $this->selectedPaymentId = optional($this->payments->first())->id ?? null;
            $this->selectedPayment = $this->payments->first();
        }
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

    function generateAnonymousName(): string
    {
        $prefix = 'anon';
        $code = strtoupper(Str::random(6)); // gunakan helper Laravel Str
        return "@{$prefix}{$code}";
    }

    public function bookingNow()
    {
        if (!$this->is_available) {
            LivewireAlert::title('Waktu Tidak Tersedia')
                ->text('iPhone sedang dibooking pada waktu tersebut. Silakan pilih waktu lain.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        $this->dispatch('open-modal', 'user-booking-create');
        // $this->payments = Payment::orderBy('created_at', 'desc')->get();
    }

    public function booking()
    {
        if (!$this->is_available) {
            LivewireAlert::title('Waktu Tidak Tersedia')
                ->text('iPhone sedang dibooking pada waktu tersebut. Silakan pilih waktu lain.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }
        $this->validate([
            'selectedIphoneId' => 'required|exists:iphones,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:15',
            'customer_email' => 'nullable|email|max:255',
            // potensial bugg
            // 'requested_booking_date' => 'required|date',
            // 'requested_time' => 'required|date_format:H:i',
            'selectedDuration' => 'required|integer|min:1',
            'selectedPrice' => 'required|numeric|min:0',
        ]);
        Booking::create([
            'iphone_id' => $this->selectedIphoneId,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->countryCode . '-' . $this->customer_phone,
            'customer_email' => $this->customer_email,

            'requested_booking_date' => Carbon::parse($this->selectedDate)->timezone('Asia/Jakarta')->toDateString(),
            'requested_time' => sprintf('%02d:%02d', $this->selectedHour, $this->selectedMinute),
            'duration' => $this->selectedDuration,
            'price' => $this->selectedPrice,
            'status' => 'pending',
            'created' => Carbon::now('Asia/Jakarta'),
            'booking_code' => self::generatePaymentCode($this->customer_name),
        ]);

        $this->dispatch('close-modal');
        $this->reset([
            'selectedIphoneId',
            'customer_name',
            'customer_phone',
            'customer_email',
            'selectedDate',
            'selectedHour',
            'selectedMinute',
            'selectedPrice',

        ]);

        $now = Carbon::now('Asia/Jakarta');
        $this->selectedHour = $now->format('H');
        $this->selectedMinute = $now->format('i');
        $this->dispatch('close-modal');
         LivewireAlert::title('Success!')
            ->text('Berhasil membuat booking cek status booking pada halaman booking')
            ->success()
            ->timer(50000)
            ->toast()
            ->position('top-end')
            ->show();
    }

    public static function generatePaymentCode($userName)
    {
        $userInitials = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $userName)), 0, 3);
        $randomString = Str::random(6);

        $code = $userInitials . $randomString;

        return $code;
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
        $this->name = $this->generateAnonymousName();
    }

    public function render()
    {
        $this->payments = Payment::where('is_active', true)->orderBy('created_at', 'desc')->get();
        $this->selectedPayment = $this->payments->firstWhere('id', $this->selectedPaymentId);

        if (!$this->selectedPayment) {
            $this->selectedPaymentId = optional($this->payments->first())->id ?? null;
            $this->selectedPayment = $this->payments->first();
        }
        return view('livewire.detail');
    }
}
