<?php

namespace App\Livewire\Dashboard;

use App\Models\Booking;
use App\Models\Revenue;
use Livewire\Component;
use Carbon\Carbon;

class Index extends Component
{

    public ?string $startDate = null;

    public ?string $endDate = null;

    public int $totalBooking = 0;

    public float $totalIncome = 0;

    public array $paymentMethods = [];

    public function mount()
    {
        $this->startDate = today()->toDateString();
        $this->endDate = today()->toDateString();

        $this->loadStatistics();
    }

    public function loadStatistics()
    {
        $query = $this->bookingQuery();

        /**
         * Total Booking
         */
        $this->totalBooking = (clone $query)->count();

        /**
         * Total Pendapatan
         */
        $this->totalIncome = Revenue::query()
            ->whereHas('booking', function ($q) {
                $q->whereBetween('created_at', [
                    Carbon::parse($this->startDate)->startOfDay(),
                    Carbon::parse($this->endDate)->endOfDay(),
                ]);
            })
            ->sum('amount');

        /**
         * Jenis Pembayaran
         */
        $this->paymentMethods = (clone $query)

            ->join('payments', 'payments.id', '=', 'bookings.payment_id')

            ->selectRaw('payments.name, COUNT(*) as total')

            ->groupBy('payments.name')

            ->pluck('total', 'payments.name')

            ->toArray();
    }

    protected function bookingQuery()
    {
        return Booking::query()
        ->where('status', 'completed') // atau status lain yang Anda anggap valid
        ->whereBetween('created_at', [
            Carbon::parse($this->startDate)->startOfDay(),
            Carbon::parse($this->endDate)->endOfDay(),
        ]);
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
