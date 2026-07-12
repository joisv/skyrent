<?php

namespace App\Livewire\Reports;

use App\Models\Booking;
use App\Models\Revenue;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Revenues extends Component
{

    public int $month;
    public int $year;

    public ?string $startDate = null;
    public ?string $endDate = null;
    public bool $disableFutureDates = true;
    public int $totalBooking = 0;
    public float $totalIncome = 0.0;
    public array $paymentMethods = [];

    public array $bookingChart = [];
    public array $paymentChart = [];

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
            ->with('payment')
            ->get()
            ->groupBy(fn($booking) => $booking->payment?->name ?? 'Belum Dipilih')
            ->map(fn($items) => $items->count())
            ->toArray();
    }

    public function refreshDashboard()
    {
        $this->loadStatistics();

        $this->loadChart();

        $this->loadPaymentChart();
    }

    protected function bookingQuery()
    {
        return Booking::query()
            ->whereBetween('bookings.created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay(),
            ]);
    }
    
    public function loadPaymentChart()
    {
        $data = $this->bookingQuery()
            ->join('payments', 'payments.id', '=', 'bookings.payment_id')
            ->selectRaw('payments.name, COUNT(bookings.id) as total')
            ->groupBy('payments.id', 'payments.name')
            ->orderBy('payments.name')
            ->get();

        $this->paymentChart = [
            'labels' => $data->pluck('name')->toArray(),
            'series' => $data->pluck('total')->toArray(),
        ];

        $this->dispatch(
            'refresh-payment-chart',
            labels: $this->paymentChart['labels'],
            series: $this->paymentChart['series']
        );
    }

    public function loadChart()
    {
        $data = $this->bookingQuery()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->bookingChart = [
            'categories' => $data->pluck('date')->toArray(),
            'series' => $data->pluck('total')->toArray(),
        ];

        $this->dispatch(
            'refresh-chart-data',
            categories: $this->bookingChart['categories'],
            series: $this->bookingChart['series']
        );
    }

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;

        $this->startDate = today()->toDateString();
        $this->endDate = null;

        $this->refreshDashboard();
    }

    public function previousMonth()
    {
        $date = Carbon::create($this->year, $this->month)->subMonth();

        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->year, $this->month)->addMonth();

        $this->month = $date->month;
        $this->year = $date->year;
    }

    public function selectDate($date)
    {
        if (
            $this->disableFutureDates &&
            Carbon::parse($date)->isAfter(today())
        ) {
            return;
        }

        if (!$this->startDate) {
            $this->startDate = $date;

            $this->refreshDashboard();

            return;
        }

        if ($this->startDate && !$this->endDate) {

            if ($date < $this->startDate) {

                $this->endDate = $this->startDate;
                $this->startDate = $date;
            } else {

                $this->endDate = $date;
            }

            $this->refreshDashboard();

            return;
        }

        $this->startDate = $date;
        $this->endDate = null;
        
        $this->refreshDashboard();
    }

    public function getCalendarProperty()
    {
        $firstDay = Carbon::create($this->year, $this->month, 1);

        $start = $firstDay->copy()->startOfWeek(Carbon::MONDAY);

        $days = [];

        for ($i = 0; $i < 42; $i++) {

            $date = $start->copy()->addDays($i);

            $days[] = [
                'date'         => $date->format('Y-m-d'),
                'day'          => $date->day,
                'currentMonth' => $date->month == $this->month,
                'today'        => $date->isToday(),

                'disabled' => $this->disableFutureDates
                    ? $date->isAfter(today())
                    : false,
            ];
        }

        return $days;
    }


    public function render()
    {

        return view('livewire.reports.revenues');
    }
}
