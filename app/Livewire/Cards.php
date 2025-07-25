<?php

namespace App\Livewire;

use App\Models\Iphones;
use Livewire\Component;

class Cards extends Component
{
    public $iphones;

    public function getIphones()
    {
        $this->iphones = Iphones::with('gallery')
            ->withCount('bookings')
            ->with(['revenues', 'bookings'])
            ->orderBy('bookings_count', 'desc') // urut berdasarkan jumlah bookings terbanyak
            ->get();
    }


    public function render()
    {
        return view('livewire.cards');
    }
}
