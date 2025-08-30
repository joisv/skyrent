<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

class Detail extends Component
{
    public $iphone;
    public $selectedPrice;
    public $selectedDateFormatted; // 25 Juli 2025, 20:51
    public int $selectedDuration; // 24

    public $selectedDate; // Date object for the selected date
    public int $selectedHour; // 20
    public int $selectedMinute; // 10

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

    public $reviews;
    public $avgRating;


    public string $bookind_code;

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
            $end = $start->copy()->addHours((int) $this->selectedDuration);
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
            $bookingEnd = $bookingStart->copy()->addHours((int) $booking->duration);

            if ($start->lt($bookingEnd) && $end->gt($bookingStart)) {
                $this->is_available = false;
                break;
            }
        }
    }

    function generateAnonymousName(): string
    {
        $prefix = 'User';
        $code = strtoupper(Str::random(6)); // gunakan helper Laravel Str
        return "{$prefix}{$code}";
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
        $bookind_code = Booking::generateBookingCode();
        $booking = Booking::create([
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
            'booking_code' => $bookind_code,
            'payment_id' => $this->selectedPayment ? $this->selectedPayment->id : null,
        ]);

        $message = "Halo {$booking->customer_name}, 👋\n\n"
            . "Terima kasih telah melakukan booking di *Skyrental* 📱✨\n\n"
            . "Berikut adalah detail booking Anda:\n"
            . "──────────────────────\n"
            . "📌 Kode Booking : *{$booking->booking_code}*\n"
            . "Perangkat    : {$booking->iphone->name}\n"
            . "Tanggal      : {$booking->requested_booking_date}\n"
            . "Waktu        : {$booking->requested_time}\n"
            . "Durasi       : {$booking->duration} jam\n"
            . "Total Biaya  : Rp" . number_format($booking->price, 0, ',', '.') . "\n"
            . "──────────────────────\n\n"
            . "Untuk memeriksa status booking Anda, silakan kunjungi link berikut:\n"
            . url('/booking-status')  . "\n\n"
            . "Mohon pastikan nomor WhatsApp yang Anda gunakan benar agar dapat menerima informasi lebih lanjut.\n\n"
            . "Terima kasih 🙏\n"
            . "*SkyRental*";

        $adminMessage = "📢 <b>Booking Baru Diterima</b>\n\n"
            . "<b>Nama</b> : {$booking->customer_name}\n"
            . "<b>HP</b>   : {$booking->customer_phone}\n"
            . "<b>Email</b>: {$booking->customer_email}\n\n"
            . "<b>Kode Booking</b>: {$booking->booking_code}\n"
            . "<b>Perangkat</b>   : {$booking->iphone->name}\n"
            . "<b>Tanggal</b>     : {$booking->requested_booking_date}\n"
            . "<b>Waktu</b>       : {$booking->requested_time}\n"
            . "<b>Durasi</b>      : {$booking->duration} jam\n"
            . "<b>Total Biaya</b>: Rp" . number_format($booking->price, 0, ',', '.') . "\n\n"
            . "🔗 <a href='" . url('/admin/bookings/') . "'>Lihat detail di Admin Panel</a>";

        $token = env('TELEGRAM_BOT_TOKEN'); // simpan token di .env
        $chatId = env('TELEGRAM_CHAT_ID'); // chat id kamu

        Http::withHeaders([
            'Authorization' => env('FONNTE_TOKEN'),
        ])->post('https://api.fonnte.com/send', [
            'target' => $this->formatPhoneNumber($booking->customer_phone), // hapus tanda "-" biar format sesuai
            'message' => $message,
        ]);

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $adminMessage,
            'parse_mode' => 'HTML',
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
        LivewireAlert::title('Booking Berhasil')
            ->text('Untuk memeriksa status booking, gunakan *Kode Booking* di halaman status booking')
            ->toast()
            ->position('top')
            ->success()
            ->timer(10000)
            ->show();

        $this->redirectRoute('booking.status', ['code' => $booking->booking_code]);
    }

    function formatPhoneNumber($phone)
    {
        // hapus semua karakter non-digit
        $digits = preg_replace('/\D/', '', $phone);

        // kalau nomor sudah diawali 62 -> biarkan
        if (substr($digits, 0, 2) === '62') {
            return $digits;
        }

        // kalau diawali 0 -> ubah ke 62
        if (substr($digits, 0, 1) === '0') {
            return '62' . substr($digits, 1);
        }

        return $digits;
    }

    public function mount(Iphones $iphone, $date, $hour, $minute)
    {
        $this->selectedDate = $date;
        $this->selectedHour = (int)$hour;
        $this->selectedMinute = (int)$minute;
        $this->iphone = $iphone;
        $now = Carbon::now('Asia/Jakarta');
        $this->selectedHour = $now->format('H');
        $this->selectedMinute = $now->format('i');
        $this->selectedIphoneId = $iphone->id;
        $this->selectedDuration = $iphone->durations->first()->hours ?? 1; // Default to first duration or 1 hour
        $this->selectedPrice = $iphone->durations->first()->pivot->price ?? 0; // Default to first duration price or 0
        $this->name = $this->generateAnonymousName();
        $this->getReviews();
    }

    #[On('get-reviews')]
    public function getReviews()
    {
        $this->reviews = Review::where('iphone_id', $this->selectedIphoneId)
            ->orderBy('created_at', 'desc')
            ->get();
        $this->avgRating = number_format(round($this->reviews->avg('rating') * 2) / 2, 1);
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
