<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Carbon\Carbon;

class Revenues extends Component
{

    public int $month;
    public int $year;

    public ?string $startDate = null;
    public ?string $endDate = null;
    public bool $disableFutureDates = true;

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;
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
            return;
        }

        if ($this->startDate && !$this->endDate) {

            if ($date < $this->startDate) {

                $this->endDate = $this->startDate;
                $this->startDate = $date;
            } else {

                $this->endDate = $date;
            }

            return;
        }

        $this->startDate = $date;
        $this->endDate = null;
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
