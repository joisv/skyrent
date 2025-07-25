<?php

namespace App\Livewire;

use App\Models\Iphones;
use Livewire\Component;

class Cards extends Component
{
    public $iphones;

    public function getIphones()
    {
        $this->iphones = Iphones::with('gallery')->orderBy('created_at', 'desc')
            ->withCount('bookings')
            ->with(['revenues', 'bookings'])
            ->get();
    }

    public function render()
    {
        return view('livewire.cards');
    }
}
