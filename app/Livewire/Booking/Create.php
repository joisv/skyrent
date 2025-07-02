<?php

namespace App\Livewire\Booking;

use App\Models\Iphones;
use Carbon\Carbon;
use Livewire\Component;

class Create extends Component
{
    public $iphones;
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;
    public $selectedIphone = null;
    public $selectedIphoneId = null;
    public $date;

    public function updatedDate($value)
    {
       dd($this->date);
    }
    
    public function mount()
    {
        $this->date = Carbon::now('Asia/Jakarta');
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
