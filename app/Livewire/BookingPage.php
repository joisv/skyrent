<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Iphones;
use Illuminate\Support\Facades\Http;
use App\Models\Revenue;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingPage extends Component
{
    public $search = '';
    public $sortField = 'created_at';       // default sort pakai status
    public $sortDirection = 'desc';      // bisa diatur 'asc' / 'desc'
    public $filterStatus = '';
    public $paginate = 50;
    public $selectedAll = false;
    public $mySelected = [];

    public $iphonesAvailable;
    public $revenueToday;
    public $returnToday;
    public $bookingToday;
    public $returnTodayCount;

    public string $message = '';
    public bool $isLate = false;
    public int $reminderId;

    public function sendReminder()
    {
        $telegramToken = config('services.telegram.bot_token');
        $chatId        = config('services.telegram.chat_id');
        $whatsappToken = config('services.fonnte.token');
        $groupId       = config('services.fonnte.group_id');

        $booking = Booking::find($this->reminderId);

        $message = "Halo {$booking->customer_name},\n\n"
            . "Ini adalah pengingat terkait penyewaan iPhone Anda di *SkyRental*.\n\n"
            . "Berikut detail penyewaan Anda:\n"
            . "--------------------------------------\n"
            . "Kode Booking : *{$booking->booking_code}*\n"
            . "Perangkat    : {$booking->iphone->name} {$booking->iphone->serial_number}\n"
            . "Tanggal Sewa : {$booking->requested_booking_date}\n"
            . "Waktu Mulai  : {$booking->requested_time}\n"
            . "Durasi       : {$booking->duration} jam\n"
            . "--------------------------------------\n\n"
            . $this->message
            . "Silakan hubungi kami jika membutuhkan perpanjangan waktu.\n\n"
            . "Terima kasih atas kerja samanya.\n"
            . "*SkyRental*";

        Http::withHeaders([
            'Authorization' => $whatsappToken,
        ])->post('https://api.fonnte.com/send', [
            'target'  => $this->formatPhoneNumber($booking->customer_phone),
            'message' => $message,
        ]);
        $this->dispatch('close-modal', 'reminder-message');
        $this->dispatch('close-modal', 'return');
        LivewireAlert::title('Pesan berhasil dikirim')
            ->position('top-end')
            ->text("Pesan berhasil dikirim ke {$booking->customer_name}")
            ->timer(4000)
            ->toast()
            ->success()
            ->show();
    }


    function formatPhoneNumber($phone, $mode = '62')
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

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $this->iphonesAvailable = Iphones::whereDoesntHave('bookings', function ($q) {
            $q->where('status', 'confirmed');
        })->get();

        $this->revenueToday = Revenue::whereDate('created_at', now()->toDateString())->sum('amount');
        $this->bookingToday = Booking::whereDate('created_at', Carbon::today())->get();
        $this->getReturnToday();
    }

    public function getReturnToday()
    {
        $this->returnToday = Booking::where('status', 'confirmed')
            ->whereDate('end_booking_date', '<=', Carbon::today())
            ->get();
    }

    public function destroy()
    {
        if (auth()->user()->can('delete')) {
            if ($this->mySelected) {
                try {
                    //code...
                    // dd('masuk ke try');
                    Booking::whereIn('id', $this->mySelected)->delete();
                    $this->mySelected = [];
                    $this->selectedAll = false;
                    LivewireAlert::title('Data berhasil dihapus')
                        ->position('top-end')
                        ->text('bulk delete data berhasil')
                        ->timer(5000)
                        ->toast()
                        ->success()
                        ->show();
                } catch (\Throwable $th) {
                    LivewireAlert::title('iPhone tidak ditemukan 1')
                        ->position('top-end')
                        ->text('tidak dapat menghapus data')
                        ->timer(5000)
                        ->toast()
                        ->error()
                        ->show();
                }
            } else {
                LivewireAlert::title('iPhone tidak ditemukan 2')
                    ->position('top-end')
                    ->text('tidak dapat menghapus data')
                    ->timer(5000)
                    ->toast()
                    ->error()
                    ->show();
            }
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->text('tidak dapat menghapus data')
                ->timer(5000)
                ->toast()
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function delete($data)
    {
        if (auth()->user()->can('delete')) {
            $this->mySelected[] = $data['data'];
            $this->destroy('deleted successfully');
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->text('tidak dapat menghapus data')
                ->timer(5000)
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function destroyAlert($value = '', $onConfirm = 'destroy')
    {

        LivewireAlert::title('Delete this posts ?')
            ->warning()
            ->toast()
            ->position('top-end')
            ->withConfirmButton('Delete')
            ->confirmButtonColor('green')
            ->cancelButtonColor('red')
            ->withCancelButton('Cancel')
            ->onConfirm($onConfirm, ['data' => $value])
            ->show();
    }

    public function getData()
    {
        $query = Booking::query()->with(['iphone', 'user']);

        // Filter status (default: confirmed)
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Search
        if ($this->search) {
            $query->search([
                'customer_name',
                'customer_phone',
                'customer_email',
                'booking_code',
                'status',
                'iphone.serial_number',
                'iphone.name',
                'user.name',
            ], $this->search);
        }

        // Sorting
        if ($this->sortField === 'status') {
            $query->orderByRaw("
            FIELD(status, 'pending', 'confirmed', 'returned', 'cancelled')
        {$this->sortDirection}");
        } elseif (in_array($this->sortField, [
            'start_booking_date',
            'end_booking_date',
            'start_time',
            'end_time',
            'price',
            'duration',
            'created_at',
            'updated_at'
        ])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    public function updatedSelectedAll($val)
    {
        $val ? $this->mySelected = $this->getData()->limit($this->paginate)->pluck('id') : $this->mySelected = [];
    }

    public function updatedMySelected()
    {
        if (count($this->mySelected) === $this->paginate) {
            $this->selectedAll = true;
        } else {
            $this->selectedAll = false;
        }
    }

    public function updatedPage($page)
    {
        $this->mySelected = [];
        $this->selectedAll = false;
    }

    // debug
    // public function sendGroupMessage()
    // {
    //     try {
    //         $response = Http::withHeaders([
    //             'Authorization' => env('FONNTE_TOKEN'),
    //         ])->post('https://api.fonnte.com/send', [
    //             'target'  => env('FONNTE_GROUP_ID'),
    //             'message' => 'test from dashboard admin',
    //         ]);

    //         if ($response->failed()) {
    //             Log::error('Fonnte group message failed', [
    //                 'status' => $response->status(),
    //                 'body'   => $response->body(),
    //             ]);

    //             return false;
    //         }

    //         return true;
    //     } catch (\Throwable $e) {

    //         Log::error('Fonnte exception', [
    //             'message' => $e->getMessage(),
    //             'line'    => $e->getLine(),
    //             'file'    => $e->getFile(),
    //         ]);

    //         return false;
    //     }
    // }

    #[On('close-modal')]
    public function reRender()
    {
        $this->revenueToday = Revenue::whereDate('created_at', now()->toDateString())->sum('amount');
    }

    public function render()
    {
        return view('livewire.booking-page', [
            'bookings' => $this->getData()->paginate($this->paginate)
        ]);
    }
}
