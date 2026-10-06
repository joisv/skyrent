<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class RentIphoneWizard extends Component
{
    public int $step = 1;

    public $countryCode = '+62';

    public $selectedIphone = null;

    public $price = 0;

    public $iphone_search = '';

    public $unit = '';

    public $is_available = false;

    public ?int $jumlah = null;

    public $totalHarga = 0;

    public int $basePricePerHour = 5000;

    // STEP 1
    public $iphones;

    public ?int $selectedIphoneId = null;

    public $requested_booking_date;

    public $requested_time;

    public $selectedDuration;

    public $selectedPrice;

    public $durations = [];

    public $end_booking_date;

    public $end_time;

    // STEP 2
    public $customer_name;

    public $customer_phone;

    public $customer_email;

    public $address;

    public $jaminan_type = 'KTP';

    public $payments;

    public $selectedPaymentId = 1;

    public $selectedPayment;

    // STEP 3
    public $sendWhatsapp = true;

    public $iphone_name;

    public $serial_number;

    public function updatedIphoneSearch($value)
    {
        $this->loadIphones();
    }

    public function updatedRequestedBookingDate()
    {
        $this->calculateEndDateTime();
        $this->loadIphones();
    }

    public function updatedRequestedTime()
    {
        $this->calculateEndDateTime();
        $this->loadIphones();
    }

    public function updatedSelectedDuration()
    {
        $this->calculateEndDateTime();
        $this->loadIphones();
    }

    public function getRequestedStartDateTime(): Carbon
    {
        $date = $this->requested_booking_date ? Carbon::parse($this->requested_booking_date)->toDateString() : Carbon::today('Asia/Jakarta')->toDateString();
        $time = $this->requested_time ? Carbon::parse($this->requested_time)->format('H:i') : Carbon::now('Asia/Jakarta')->format('H:i');

        return Carbon::parse("{$date} {$time}", 'Asia/Jakarta');
    }

    public function getRequestedEndDateTime(): Carbon
    {
        $start = $this->getRequestedStartDateTime();
        $duration = (int) ($this->selectedDuration ?: 1);

        return $start->copy()->addHours(max(1, $duration));
    }
    
    #[On('iphone-selected')]
    public function setIphone(int $iphoneId)
    {
        $this->selectedIphoneId = $iphoneId;
        $this->getDurations();
        $this->dispatch('display-duration-options');
    }
    
    public function testDispatchEvent()
    {
        $this->dispatch('test-event');
    }
    
    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'selectedIphoneId' => 'required|exists:iphones,id',
                'requested_booking_date' => 'required|date',
                'requested_time' => 'required|date_format:H:i',
                'selectedDuration' => 'required|integer|min:1',
                'selectedPrice' => 'required|numeric|min:0',
            ],
            2 => [
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:30',
                'customer_email' => 'nullable|email|max:255',
                'address' => 'required|string|max:255',
                'jaminan_type' => 'required|in:KTP,KK,Kartu Pelajar,SIM,Kartu Identitas Mahasiswa,Kartu Identitas Anak',

            ],
            3 => [
                'customer_name' => 'required|min:3',
                'customer_phone' => 'required',
            ],
            default => [],
        };
    }

    public function next(): void
    {
        $this->validate();

        if ($this->step === 1) {
            $this->handleStepOne();
        }

        if ($this->step === 2) {
            $whatsappToken = config('services.fonnte.token');
            // cek apakah nomor whatsapp customer valid
            $check = Http::timeout(10)->withHeaders([
                'Authorization' => $whatsappToken,
            ])->post('https://api.fonnte.com/validate', [
                'target' => $this->formatPhoneNumber($this->customer_phone),
            ]);
    
            $data = $check->json();

            if (!empty($data['not_registered'])) {
                LivewireAlert::title('Nomor Tidak Terdaftar')
                    ->text('Nomor WhatsApp yang Anda masukkan tidak terdaftar.')
                    ->warning()
                    ->toast()
                    ->position('top-end')
                    ->show();

                return;
            }
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

        if (! $this->end_booking_date || ! $this->end_time) {
            LivewireAlert::title('Gagal Hitung Waktu')
                ->text('Gagal menghitung waktu selesai booking.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();

            return;
        }

        // Gabungkan tanggal dan waktu dari booking sekarang
        $start = $this->getRequestedStartDateTime();
        $end = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($this->end_booking_date)->format('Y-m-d') . ' ' . $this->end_time);

        $iphone = Iphones::find($this->selectedIphoneId);
        if (! $iphone) {
            LivewireAlert::title('Unit Tidak Ditemukan')
                ->text('Unit iPhone tidak ditemukan.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        // 🔍 Cek ketersediaan unit fisik iPhone untuk rentang waktu yang dipilih
        if (! $iphone->isAvailableForPeriod($start, $end, null, true)) {
            LivewireAlert::title('Unit Tidak Tersedia')
                ->text("Unit {$iphone->name} ({$iphone->serial_number}) tidak tersedia untuk jadwal yang dipilih. Mohon pilih unit lain atau sesuaikan tanggal sewa.")
                ->error()
                ->toast()
                ->position('top-end')
                ->show();

            $this->loadIphones();
            return;
        }
    }

    public function calculateEndDateTime()
    {

        if (! $this->requested_booking_date || ! $this->requested_time || ! $this->selectedDuration) {
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
        $telegramToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');
        $whatsappToken = config('services.fonnte.token');
        $groupId = config('services.fonnte.group_id');

        if ($this->jumlah > 1) {
            $this->selectedDuration = $this->selectedDuration * 24;
        }

        $this->validate([
            'selectedIphoneId' => 'required|exists:iphones,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:30',
            'customer_email' => 'nullable|email|max:255',
            'requested_booking_date' => 'required|date',
            'requested_time' => 'required|date_format:H:i',
            'selectedDuration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $iphone = Iphones::where('id', $this->selectedIphoneId)->lockForUpdate()->first();
            if (! $iphone) {
                DB::rollBack();
                LivewireAlert::title('Unit Tidak Ditemukan')
                    ->text('Unit iPhone tidak ditemukan.')
                    ->error()
                    ->toast()
                    ->position('top-end')
                    ->show();
                return;
            }

            $user = auth()->user();
            if ($user && method_exists($user, 'hasRole') && !$user->hasRole('super-admin') && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || (!empty($user->affiliate_id) && !$user->hasRole('admin')))) {
                if ($user->affiliate_id && $iphone->affiliate_id != $user->affiliate_id) {
                    DB::rollBack();
                    LivewireAlert::title('Akses Ditolak')
                        ->text('Unit iPhone ini tidak terdaftar pada affiliate Anda.')
                        ->error()
                        ->toast()
                        ->position('top-end')
                        ->show();
                    return;
                }
            }

            $start = $this->getRequestedStartDateTime();
            $duration = (int) $this->selectedDuration;
            $end = $start->copy()->addHours(max(1, $duration));

            if (! $iphone->isAvailableForPeriod($start, $end, null, true)) {
                DB::rollBack();
                LivewireAlert::title('Unit Sudah Dibooking')
                    ->text("Maaf, unit iPhone {$iphone->name} ({$iphone->serial_number}) baru saja dibooking oleh pengguna lain untuk jadwal tersebut.")
                    ->error()
                    ->toast()
                    ->position('top-end')
                    ->show();

                $this->loadIphones();
                return;
            }

            $booking = Booking::create([
                'iphone_id' => $this->selectedIphoneId,
                'customer_name' => $this->customer_name,
                'customer_phone' => $this->countryCode . '-' . $this->customer_phone,
                'customer_email' => $this->customer_email,
                'requested_booking_date' => $this->requested_booking_date,
                'requested_time' => $this->requested_time,
                // 'end_booking_date' => $end->toDateString(),
                // 'end_time' => $end->format('H:i'),
                'duration' => $this->selectedDuration,
                'price' => $this->selectedPrice,
                'status' => 'confirmed',
                'created' => Carbon::now('Asia/Jakarta'),
                'booking_code' => Booking::generateBookingCode(),
                'payment_id' => $this->selectedPayment ? $this->selectedPayment->id : null,
                'address' => $this->address,
                'pickup_type' => 'pickup',
                'jaminan_type' => $this->jaminan_type === 'Kartu Identitas Anak' ? 'Kartu Pelajar' : $this->jaminan_type,
                'kia' => $this->jaminan_type === 'Kartu Identitas Anak' ? true : false,
                'user_id' => auth()->user()->id ?? null,
                'affiliate_id' => auth()->user()->affiliate_id ?? null,
            ]);

            // debug off
            $message = "Halo {$booking->customer_name},\n\n"
                . "Terima kasih telah melakukan booking di *SkyRental*.\n\n"
                . "Berikut adalah detail booking Anda:\n"
                . "--------------------------------------\n"
                . "Kode Booking : *{$booking->booking_code}*\n"
                . "Perangkat    : {$booking->iphone->name} {$booking->iphone->serial_number}\n"
                . "Tanggal      : {$booking->requested_booking_date}\n"
                . "Waktu        : {$booking->requested_time}\n"
                . "Durasi       : {$booking->duration} jam\n"
                . 'Total Biaya  : Rp' . number_format($booking->price, 0, ',', '.') . "\n"
                . "--------------------------------------\n\n"
                . "Mohon segera melakukan pembayaran *maksimal 30 menit* setelah pesan ini diterima.\n"
                . 'Apabila pembayaran belum kami terima hingga batas waktu tersebut, '
                . "maka booking akan *dibatalkan secara otomatis*.\n\n"
                . 'Setelah melakukan pembayaran, silakan lakukan konfirmasi dengan membalas pesan ini '
                . "atau mengirim bukti pembayaran melalui WhatsApp ini.\n\n"
                . "Untuk memeriksa status booking, silakan kunjungi:\n"
                . url('/booking-status') . "\n\n"
                . "Terima kasih atas kerja samanya.\n"
                . 'SkyRental';

            $groupMessage = "BOOKING BARU MASUK\n\n"
                . "Perangkat    : {$booking->iphone->name} {$booking->iphone->serial_number}\n"
                . "Nama         : {$booking->customer_name}\n"
                . "No. HP       : {$booking->customer_phone}\n"
                . "Alamat       : {$booking->address}\n"
                . "Jaminan       : {$booking->jaminan_type}\n"
                . "Email        : {$booking->customer_email}\n\n"
                . "Kode Booking : {$booking->booking_code}\n"
                . "Tanggal      : {$booking->requested_booking_date}\n"
                . "Waktu        : {$booking->requested_time}\n"
                . "Durasi       : {$booking->duration} jam\n"
                . 'Total Biaya  : Rp' . number_format($booking->price, 0, ',', '.') . "\n\n"
                . "Status       : {$booking->status} \n"
                . "Admin Panel:\n"
                . url('/admin/bookings/');

            $adminMessage = "<b>Booking Baru Diterima</b>\n\n"
                . "<b>Nama</b> : {$booking->customer_name}\n"
                . "<b>HP</b>   : {$booking->customer_phone}\n"
                . "<b>Email</b>: {$booking->customer_email}\n\n"
                . "<b>Kode Booking</b>: {$booking->booking_code}\n"
                . "<b>Perangkat</b>   : {$booking->iphone->name}\n"
                . "<b>Tanggal</b>     : {$booking->requested_booking_date}\n"
                . "<b>Waktu</b>       : {$booking->requested_time}\n"
                . "<b>Durasi</b>      : {$booking->duration} jam\n"
                . '<b>Total Biaya</b>: Rp' . number_format($booking->price, 0, ',', '.') . "\n\n"
                . "🔗 <a href='" . url('/admin/bookings/' . $booking->id) . "'>Lihat detail di Admin Panel</a>";

            if ($this->sendWhatsapp) {

                Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => $whatsappToken,
                    ])
                    ->post('https://api.fonnte.com/send', [
                        'target' => $this->formatPhoneNumber($booking->customer_phone),
                        'message' => $message,
                    ]);
            }

            DB::commit();

            // Kirim ke Telegram
            if ($telegramToken && $chatId) {
                try {
                    Http::timeout(10)->post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $adminMessage,
                        'parse_mode' => 'HTML',
                    ]);
                } catch (\Exception $e) {
                    logger()->error('Telegram Error: ' . $e->getMessage());
                }
            }

            try {
                Http::timeout(10)->withHeaders([
                    'Authorization' => $whatsappToken,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $groupId,
                    'message' => $groupMessage,
                ]);
            } catch (\Exception $e) {
                logger()->error('Fonnte Group Error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {

            DB::rollBack();

            logger()->error($e->getMessage());

            LivewireAlert::title('Error')
                ->text('Terjadi kesalahan saat membuat booking.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
        }

        // Reset
        $this->dispatch('open-payment-modal', booking_id: $booking->id);
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
            'price',
        ]);
        $this->sendWhatsapp = true;
        $this->step = 1;
        $this->requested_booking_date =
            Carbon::today('Asia/Jakarta')->format('Y-m-d');
        $this->requested_time = Carbon::now('Asia/Jakarta')->format('H:i');
        $this->loadIphones();
        // LivewireAlert::title('Success!')
        //     ->text('Booking berhasil disimpan.')
        //     ->success()
        //     ->toast()
        //     ->position('top-end')
        //     ->show();
    }

    public function selectIphone(int $iphoneId, string $name, $serial_number)
    {
        $iphone = Iphones::find($iphoneId);
        if (! $iphone) {
            LivewireAlert::title('Unit Tidak Ditemukan')
                ->text('iPhone yang dipilih tidak ditemukan.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        $start = $this->getRequestedStartDateTime();
        $end = $this->getRequestedEndDateTime();

        if (! $iphone->isAvailableForPeriod($start, $end, null, true)) {
            LivewireAlert::title('Unit Sedang Disewa')
                ->text("Unit {$iphone->name} ({$iphone->serial_number}) tidak tersedia untuk jadwal yang dipilih.")
                ->warning()
                ->toast()
                ->position('top-end')
                ->show();

            $this->loadIphones();
            return;
        }

        $this->selectedIphoneId = $iphoneId;
        $this->iphone_name = $name;
        $this->serial_number = $serial_number;
        $this->getDurations();
        $this->dispatch('display-duration-options');
        $this->loadIphones();
    }

    public function updatedSelectedPaymentId()
    {
        $this->selectedPayment = $this->payments->firstWhere('id', $this->selectedPaymentId);
        if (! $this->selectedPayment) {
            $this->selectedPaymentId = optional($this->payments->first())->id ?? null;
            $this->selectedPayment = $this->payments->first();
        }
    }

    public function mount()
    {
        $this->payments = Payment::orderBy('created_at', 'desc')->get();
        $this->selectedPayment = $this->payments->firstWhere('id', $this->selectedPaymentId);

        if (! $this->selectedPayment) {
            $this->selectedPaymentId = optional($this->payments->first())->id ?? null;
            $this->selectedPayment = $this->payments->first();
        }
        $now = Carbon::now('Asia/Jakarta');

        $this->requested_booking_date = $now->toDateString(); // Y-m-d
        $this->requested_time = $now->format('H:i');
        $this->loadIphones();
    }

    public function formatPhoneNumber($phone, $mode = '62')
    {
        // hapus semua karakter non-digit
        $digits = preg_replace('/\D/', '', $phone);

        // normalisasi ke format 62
        if (substr($digits, 0, 2) === '62') {
            $normalized = $digits;
        } elseif (substr($digits, 0, 1) === '0') {
            $normalized = '62' . substr($digits, 1);
        } else {
            $normalized = '62' . $digits;
        }

        if ($mode === '0') {
            return '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    #[On('reload-iphone')]
    public function loadIphones()
    {
        $user = auth()->user();
        $start = $this->getRequestedStartDateTime();
        $end = $this->getRequestedEndDateTime();

        $this->iphones = Iphones::query()
            ->with([
                'gallery',
                'durations',
                'bookings' => function ($query) {
                    $query->whereIn('status', ['pending', 'confirmed', 'rented', 'disewa'])
                          ->whereDoesntHave('returns');
                },
            ])
            ->when($user && method_exists($user, 'hasRole') && !$user->hasRole('super-admin') && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || (!empty($user->affiliate_id) && !$user->hasRole('admin'))), function ($query) use ($user) {
                $query->where('affiliate_id', $user->affiliate_id);
            })
            // ->when($user->hasRole('super-admin'), function ($query) {
            //     $query->whereNull('affiliate_id');
            // })
            ->when($this->iphone_search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->iphone_search . '%')
                        ->orWhere('serial_number', 'like', '%' . $this->iphone_search . '%');
                });
            })
            ->get()
            ->map(function ($iphone) use ($start, $end) {
                $available = $iphone->isAvailableForPeriod($start, $end);
                $iphone->is_available = $available;
                $iphone->setAttribute('is_available', $available);

                return $iphone;
            });
    }


    // #[On('booking-time-set')]
    // public function receiveBookingTime(string $date, string $time): void
    // {
    //     $this->requested_booking_date = $date;
    //     $this->requested_time = $time;
    // }

    public function setCustom($unit)
    {
        // dd($unit);
        $this->unit = $unit;
        $this->basePricePerHour = Iphones::find($this->selectedIphoneId)
            ?->durations()
            ->where('hours', 24)
            ->first()
            ?->pivot
            ?->price ?? 5000;
        $this->updateDurationAndPrice();
        $this->calculateEndDateTime();
        $this->loadIphones();
    }

    public function updatedJumlah()
    {
        $this->updateDurationAndPrice();
        $this->calculateEndDateTime();
        $this->loadIphones();
    }

    private function updateDurationAndPrice()
    {

        if (empty($this->unit) || empty($this->jumlah)) {
            $this->selectedDuration = 0;
            $this->selectedPrice = 0;

            return;
        }

        if ($this->unit === 'Jam' && $this->jumlah < 24) {
            $this->jumlah = 24;
        }

        if ($this->jumlah < 1) {
            $this->selectedDuration = 0;
            $this->selectedPrice = 0;

            return;
        }

        if ($this->basePricePerHour === 5000) {
            // code...
            switch ($this->unit) {

                case 'Hari':
                    $this->selectedDuration = $this->jumlah * 24;
                    break;
                case 'Minggu':
                    $this->selectedDuration = $this->jumlah * 24 * 7;
                    break;
                case 'Bulan':
                    $this->selectedDuration = $this->jumlah * 24 * 30; // asumsi 30 hari
                    break;
                default:
                    $this->selectedDuration = $this->jumlah;
                    break;
            }
        } else {
            switch ($this->unit) {

                case 'Hari':
                    $this->selectedDuration = $this->jumlah;
                    break;
                case 'Minggu':
                    $this->selectedDuration = $this->jumlah * 7;
                    break;
                case 'Bulan':
                    $this->selectedDuration = $this->jumlah * 30; // asumsi 30 hari
                    break;
                default:
                    $this->selectedDuration = $this->jumlah;
                    break;
            }
        }

        // hitung harga (contoh sederhana per jam)
        $this->selectedPrice = $this->selectedDuration * $this->basePricePerHour;
    }

    public function render()
    {
        return view('livewire.rent-iphone-wizard');
    }
}
