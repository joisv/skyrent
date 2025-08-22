<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\ReturnIphone;
use Carbon\Carbon;
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
        $this->condition = '';

        $this->dispatch('saved'); // bisa dipakai untuk toast
    }

    public function render()
    {
        return view('livewire.detail-booking');
    }
}
