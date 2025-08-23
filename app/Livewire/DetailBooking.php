<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\ReturnIphone;
use Carbon\Carbon;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class DetailBooking extends Component
{
    public $detailBookingIphones;
    public $returns;
    public $condition;

    #[On('get-detail')]
    public function getDetailIphone($id)
    {
        // dd($id);
        $this->detailBookingIphones = Booking::with(['revenue', 'iphone', 'payment', 'returns'])->findOrFail($id);
        $this->returns = $this->detailBookingIphones->returns()->latest()->get();
    }

    public function save()
    {
        $return = new ReturnIphone([
            'returned_at' => Carbon::now('Asia/Jakarta'),
            'condition'   => $this->condition,
        ]);

        // assign booking_id dulu
        $return->booking()->associate($this->detailBookingIphones);

        // baru hitung penalty
        $return->penalty_fee = $return->calculatePenalty();

        // simpan
        $return->save();


        // reset input
        $this->reset('condition');
        $this->updateStatusBooking($this->detailBookingIphones->id, 'returned');
        $this->dispatch('close-modal');

    }

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
    public function refreshStatus()
    {
    }
    
    public function render()
    {
        return view('livewire.detail-booking');
    }
}
