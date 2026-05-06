<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\ReturnIphone;
use App\Models\Revenue;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Http;
use phpDocumentor\Reflection\Types\This;

class DetailBooking extends Component
{
    public Booking $booking;

    public $detailBookingIphones;
    public $returns;
    public $condition;
    public $bookingId;
    public int $hours = 1;
    public bool $available = true;
    public $newEnd = '';
    public $durations;
    public $selectedDurationId = null;
    public int $multiplier = 1;
    public $totalHours;
    public $sumRevenues;

    public bool $is_late;
    public $diff_hours;
    public $penaltyFee = 0;
    // Pengembalian

    public function updateStatusIphone(string $status = 'returned')
    {
        $booking = Booking::find($this->detailBookingIphones->id);
        $booking->update(['status' => $status]);
        ReturnIphone::create([
            'booking_id' => $this->bookingId,
            'penalty_fee' => $this->is_late ? $this->penaltyFee : 0,
            'returned_at' => now('Asia/Jakarta'),
            'condition'   => 'Pengembalian berhasil.',
        ]);
        $this->dispatch('success-save');
        $this->dispatch('close-modal');
        $this->reset([
            'penaltyFee',
            'is_late',
            'diff_hours'
        ]);
        LivewireAlert::title('Status berhasil diubah')
            ->position('top-end')
            ->text('Status booking telah diperbarui')
            ->toast()
            ->success()
            ->show();
    }

    public function savePenaltyFee()
    {
        Revenue::create([
            'booking_id' => $this->bookingId,
            'amount'     => $this->penaltyFee,
            'type'       => 'penalty',
            'created'    => now(),
        ]);
        $this->updateStatusIphone();
    }

    public function returnIphone()
    {
        if ($this->is_late && $this->detailBookingIphones->status == 'confirmed') {
            $this->dispatch('open-modal', 'tambah-denda');
        } elseif ($this->detailBookingIphones->status == 'confirmed') {
            $this->updateStatusIphone();
        } elseif ($this->is_late) {
            $this->updateStatusIphone();
        } else {
            LivewireAlert::title('Tidak bisa merubah status iPhone')
                ->text(dd($this->is_late))
                ->position('top-end')
                ->toast()
                ->error()
                ->show();
        }
    }

    public function validateReturnTime($endDate, $endTime)
    {
        $endDateTime = Carbon::parse($endDate . ' ' . $endTime);
        $now = Carbon::now();

        $diffInMinutes = $endDateTime->diffInMinutes($now, false);

        $diffInHours = $diffInMinutes / 60;

        $isLate = $diffInMinutes > 90;

        return [
            'is_late' => $isLate,
            'diff_hours' => round($diffInHours, 2),
        ];
    }

    public function getRevenue()
    {
        $this->sumRevenues = Booking::findOrFail($this->booking->id)->revenue()->sum('amount');
    }

    #[On('get-detail')]
    public function getDetailIphone($id)
    {
        $this->detailBookingIphones = Booking::with([
            'revenue',
            'iphone.durations',
            'payment',
            'returns',
        ])->findOrFail($id);

        $this->booking = $this->detailBookingIphones;
        $this->durations = $this->booking->iphone->durations
            ->sortBy('hours')
            ->values();

        $returnStatus = $this->validateReturnTime($this->detailBookingIphones->end_booking_date, $this->detailBookingIphones->end_time);
        $nonLateStatuses = ['returned', 'canceled', 'pending'];

        $this->is_late = in_array($this->detailBookingIphones->status, $nonLateStatuses)
            ? false
            : $returnStatus['is_late'];
        $this->diff_hours = $returnStatus['diff_hours'];

        $this->bookingId = $this->detailBookingIphones->id;
        $this->getRevenue();
        $this->durations = $this->booking->iphone
            ->durations()
            ->orderBy('hours')
            ->get();
        $this->returns = $this->detailBookingIphones->returns()->latest()->get();
        $this->recalculate();
    }

    #[On('modal-durasi')]
    public function reRender()
    {
        $this->durations = $this->booking->iphone->durations
            ->sortBy('hours')
            ->values();
    }

    public function updated($property)
    {
        $this->durations = $this->booking->iphone->durations
            ->sortBy('hours')
            ->values();

        if (in_array($property, ['selectedDurationId', 'multiplier'])) {
            $this->recalculate();
        }
    }

    private function recalculate()
    {
        if (! $this->selectedDurationId || $this->multiplier < 1) {
            $this->resetPreview();
            return;
        }

        $duration = $this->durations
            ->firstWhere('id', $this->selectedDurationId);

        if (! $duration) {
            $this->resetPreview();
            return;
        }

        $this->totalHours = $duration->hours * $this->multiplier;

        $end = Carbon::parse(
            "{$this->booking->end_booking_date} {$this->booking->end_time}",
            'Asia/Jakarta'
        );

        $newEnd = $end->copy()->addHours($this->totalHours);
        $this->newEnd = $newEnd->format('d M Y H:i');

        $this->available = $this->booking->canExtend($this->totalHours);
    }

    public function recalculatePreview()
    {
        if (! $this->selectedDurationId) {
            $this->totalHours = 0;
            $this->newEnd = null;
            return;
        }

        $duration = $this->booking
            ->iphone
            ->durations()
            ->where('durations.id', $this->selectedDurationId)
            ->first();

        if (! $duration) {
            $this->totalHours = 0;
            $this->newEnd = null;
            return;
        }

        // Hitung total jam
        $this->totalHours = $duration->hours * $this->multiplier;

        $endDateTime = $this->booking->end_booking_date . ' ' . $this->booking->end_time;

        // Hitung waktu selesai baru
        $this->newEnd = \Carbon\Carbon::parse($endDateTime)
            ->addHours($this->totalHours)
            ->format('d M Y H:i');
    }


    private function resetPreview()
    {
        $this->totalHours = 0;
        $this->newEnd = '';
        $this->available = false;
    }


    public function extend()
    {
        if (! $this->available || $this->totalHours < 1) {
            return;
        }

        $duration = $this->booking
            ->iphone
            ->durations()
            ->where('durations.id', $this->selectedDurationId)
            ->first();

        if (! $duration) return;

        $this->booking->extendHours($this->totalHours);
        $this->booking->update([
            'reminder_sent' => false,
        ]);
        Revenue::create([
            'booking_id' => $this->booking->id,
            'amount' => $duration->pivot->price * $this->multiplier,
            'type' => 'extend',
            'created' => now('Asia/Jakarta'),
        ]);

        // booking message
        $successExtendMessage = "Halo {$this->booking->customer_name},\n\n"
            . "Penambahan durasi sewa Anda di *SkyRental* telah *berhasil dikonfirmasi*.\n\n"
            . "Berikut detail terbaru booking Anda:\n"
            . "--------------------------------------\n"
            . "Kode Booking : *{$this->booking->booking_code}*\n"
            . "Perangkat    : {$this->booking->iphone->name} {$this->booking->iphone->serial_number}\n"
            . "Tambah Waktu : {$this->totalHours} jam\n"
            . "Selesai  : {$this->newEnd} \n"
            . "--------------------------------------\n\n"
            . "Penambahan waktu telah kami konfirmasi.\n"
            . "Silakan menggunakan perangkat sesuai durasi terbaru.\n\n"
            . "Terima kasih atas kepercayaan Anda 🙏\n"
            . "SkyRental";

        $adminExtendSuccessMessage = "<b>Tambah Durasi Berhasil</b>\n\n"
            . "<b>Nama</b> : {$this->booking->customer_name}\n"
            . "<b>HP</b>   : {$this->booking->customer_phone}\n"
            . "<b>Email</b>: {$this->booking->customer_email}\n\n"
            . "<b>Kode Booking</b> : {$this->booking->booking_code}\n"
            . "<b>Perangkat</b>    : {$this->booking->iphone->name} {$this->booking->iphone->serial_number}\n"
            . "<b>Tambah Waktu</b> : {$this->totalHours} jam\n"
            . "<b>Waktu Selesai</b>: {$this->newEnd}\n\n"
            . "<b>Status</b>       : Berhasil\n\n"
            . "🔗 <a href='" . url('/admin/bookings/' . $this->booking->id) . "'>Lihat detail di Admin Panel</a>";

        $telegramToken = config('services.telegram.bot_token');
        $chatId        = config('services.telegram.chat_id');
        $whatsappToken = config('services.fonnte.token');

        // Kirim WhatsApp
        Http::withHeaders([
            'Authorization' => $whatsappToken,
        ])->post('https://api.fonnte.com/send', [
            'target'  => $this->formatPhoneNumber($this->booking->customer_phone),
            'message' => $successExtendMessage,
        ]);

        // Kirim Telegram
        Http::post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $adminExtendSuccessMessage,
            'parse_mode' => 'HTML',
        ]);

        // Reset UI
        $this->selectedDurationId = null;
        $this->multiplier = 1;
        $this->durations = $this->booking->iphone->durations
            ->sortBy('hours')
            ->values();
        $this->getRevenue();
        $this->resetPreview();
        $this->dispatch('modal-durasi');
        LivewireAlert::title('Berhasil menambahkandurasi!')
            ->text('Durasi berhasil ditambahkan ke booking')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function changeMultiplier(string $action): void
    {
        $this->durations = $this->booking->iphone->durations
            ->sortBy('hours')
            ->values();

        switch ($action) {
            case 'increase':
                $this->multiplier++;
                break;

            case 'decrease':
                if ($this->multiplier > 1) {
                    $this->multiplier--;
                }
                break;
        }
        $this->recalculatePreview();
    }

    // Tidak digunakan untuk sementara
    // public function save()
    // {
    //     $return = new ReturnIphone([
    //         'returned_at' => Carbon::now('Asia/Jakarta'),
    //         'condition'   => $this->condition,
    //     ]);

    //     // assign booking_id dulu
    //     $return->booking()->associate($this->detailBookingIphones);

    //     // baru hitung penalty
    //     $return->penalty_fee = $return->calculatePenalty();

    //     // simpan
    //     $return->save();


    //     // reset input
    //     $this->reset('condition');
    //     $this->updateStatusBooking($this->detailBookingIphones->id, 'returned');
    //     $this->dispatch('close-modal');
    // }




    public function updateStatusBooking($bookingId, $status)
    {
        if (auth()->user()->can('update')) {
            try {
                $booking = Booking::find($bookingId);
                $booking->update(['status' => $status]);
                LivewireAlert::title('Status berhasil diubah')
                    ->position('top-end')
                    ->text('Status booking telah diperbarui')
                    ->toast()
                    ->success()
                    ->show();
            } catch (\Throwable $th) {
                LivewireAlert::title('Gagal mengubah status')
                    ->position('top-end')
                    ->text($th)
                    ->timer(5000)
                    ->error()
                    ->show();
            }
        } else {
            LivewireAlert::title('Kamu tidak memiliki izin')
                ->position('top-end')
                ->text('Tidak dapat mengubah status booking')
                ->timer(5000)
                ->error()
                ->show();
        }

        $this->dispatch('status-updated')->self(); // bisa dipakai untuk toast
    }

    #[On('status-updated')]
    public function refreshStatus() {}

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

    public function render()
    {
        return view('livewire.detail-booking');
    }
}
