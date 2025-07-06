<?php

namespace App\Livewire;

use App\Models\Booking;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class BookingPage extends Component
{
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;
    public $selectedAll = false;
    public $mySelected = [];

    public function getData()
    {
        $query = Booking::query()->with('iphone');

        if ($this->search) {
            $query->search([
                'customer_name',
                'customer_phone',
                'customer_email',
                'iphone.name' // relasi
            ], $this->search);
        }

        if (in_array($this->sortField, [
            'start_booking_date',
            'end_booking_date',
            'start_time',
            'end_time',
            'price',
            'duration',
            'status',
            'created_at',
            'updated_at'
        ])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }


    public function render()
    {
        return view('livewire.booking-page', [
            'bookings' => $this->getData()->paginate($this->paginate)
        ]);
    }
}
