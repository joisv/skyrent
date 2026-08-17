<?php

namespace App\Livewire\Affiliate;

use App\Models\Affiliate;
use App\Models\BookingPayment;
use App\Models\Revenue as ModelsRevenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Revenue extends Component
{
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public $affiliates;
    public $affiliateRevenue;
    public $affiliateBookingCount;
    public $affiliatePayments;
    public $revenueToday;
    public $bookingToday;
    public $paymentsList;
    public $startDate;
    public $endDate;

    public function render()
    {
        return view('livewire.affiliate.revenue');
    }

    public function mount()
    {
        $this->getAffiliateRevenueToday();
        $this->loadAffiliateRevenue();
        $this->getPaymentList();
        $this->startDate = now()->subDays(6)->toDateString();
        $this->endDate = now()->toDateString();
    }

    protected function getDateRange(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();

        $end = Carbon::parse(
            $this->endDate ?: $this->startDate
        )->endOfDay();

        return [$start, $end];
    }

    public function getPaymentList()
    {
        [$start, $end] = $this->getDateRange();

        $query = BookingPayment::query()
            ->whereBetween('paid_at', [$start, $end]);

        $user = auth()->user();

        if ($user->hasRole('affiliate-admin')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where(function ($query) use ($user) {
                    $query->where('affiliate_id', $user->affiliate_id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('affiliate_id')
                                ->where('user_id', $user->id);
                        });
                });
            });
        }

        $this->paymentsList = $query
            ->with([
                'booking.iphone',
                'booking.affiliate',
                'payment',
                'user',
            ])
            ->latest('paid_at')
            // ->paginate($this->paginate)
            ->get();
    }

    public function loadAffiliateRevenue()
    {
        $user = auth()->user();

        $query = BookingPayment::with([
            'booking.iphone',
            'booking.affiliate',
            'payment',
            'user',
        ]);

        // Affiliate admin hanya melihat affiliate miliknya
        if ($user->hasRole('affiliate-admin')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('affiliate_id', $user->affiliate_id);
            });
        }

        $payments = $query
            ->latest('paid_at')
            ->get();

        $this->affiliateRevenue = $payments->sum('amount');

        $this->affiliateBookingCount = $payments
            ->pluck('booking_id')
            ->unique()
            ->count();

        $this->affiliatePayments = $payments;
    }

    public function getAffiliateRevenueToday()
    {
        $user = auth()->user();

        $query = BookingPayment::query()
            ->whereDate('paid_at', now('Asia/Jakarta')->toDateString());

        // Affiliate admin hanya melihat miliknya
        if ($user->hasRole('affiliate-admin')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('affiliate_id', $user->affiliate_id);
            });
        }

        $this->revenueToday = $query->sum('amount');

        $this->bookingToday = (clone $query)
            ->distinct('booking_id')
            ->count('booking_id');
    }
}
