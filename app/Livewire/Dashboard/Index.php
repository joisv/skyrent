<?php

namespace App\Livewire\Dashboard;

use App\Models\Booking;
use App\Models\BookingPayment;
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

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $user = auth()->user();
        $isAffiliateScoped = ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin'))));
        $affiliateId = $isAffiliateScoped ? $user->affiliate_id : null;

        /**
         * Total Pendapatan dari Kasir/Ledger Pembayaran Nyata
         */
        $paymentQuery = BookingPayment::active()->whereBetween('paid_at', [$start, $end]);
        if ($isAffiliateScoped && $affiliateId) {
            $paymentQuery->where('booking_payments.affiliate_id', $affiliateId);
        }
        $this->totalIncome = (float) $paymentQuery->sum('amount');

        /**
         * Jenis Pembayaran
         */
        $pmQuery = BookingPayment::active()
            ->whereBetween('paid_at', [$start, $end])
            ->join('payments', 'payments.id', '=', 'booking_payments.payment_id');

        if ($isAffiliateScoped && $affiliateId) {
            $pmQuery->where('booking_payments.affiliate_id', $affiliateId);
        }

        $this->paymentMethods = $pmQuery
            ->selectRaw('payments.name, COUNT(*) as total')
            ->groupBy('payments.name')
            ->pluck('total', 'payments.name')
            ->toArray();
    }

    protected function bookingQuery()
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $user = auth()->user();
        $isAffiliateScoped = ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin'))));
        $affiliateId = $isAffiliateScoped ? $user->affiliate_id : null;

        $query = Booking::query()
            ->whereIn('status', ['confirmed', 'rented', 'disewa', 'returned', 'completed'])
            ->whereBetween('created_at', [$start, $end]);

        if ($isAffiliateScoped && $affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
