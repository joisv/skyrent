<?php

namespace App\Livewire\Affiliate;

use App\Models\Affiliate;
use App\Models\BookingPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
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
    public $affiliateRevenue = 0;
    public $affiliateBookingCount = 0;
    public $affiliatePayments;
    public $revenueToday = 0;
    public $bookingToday = 0;
    public $paymentsList;
    public $startDate;
    public $endDate;

    public function render()
    {
        return view('livewire.affiliate.revenue');
    }

    public function mount()
    {
        $this->startDate = now()->subDays(6)->toDateString();
        $this->endDate = now()->toDateString();
        $this->refreshData();
    }

    public function refreshData()
    {
        $this->getAffiliateRevenueToday();
        $this->loadAffiliateRevenue();
        $this->getPaymentList();
    }

    protected function getDateRange(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : null;

        $end = Carbon::parse(
            $this->endDate ?: ($this->startDate ?: now()->toDateString())
        )->endOfDay();

        return [$start, $end];
    }

    /**
     * Apply strict affiliate ownership scope to the BookingPayment query.
     * Affiliate revenue is determined by the affiliate that owns the transaction/booking,
     * NOT by the user who created the record (e.g. super-admin).
     */
    protected function applyAffiliateScope($query)
    {
        $user = auth()->user();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return $query;
        }

        // super-admin has global access to all affiliate revenue
        if ($user->hasRole('super-admin')) {
            return $query;
        }

        // admin without affiliate_id also has global access
        if ($user->hasRole('admin') && empty($user->affiliate_id)) {
            return $query;
        }

        // affiliate, affiliate-admin, or admin with an affiliate_id
        $affiliateId = $user->affiliate_id;

        if (!$affiliateId) {
            // An affiliate user without an assigned affiliate has no revenue scope
            $query->whereRaw('1 = 0');
            return $query;
        }

        // Scope to booking ownership:
        // 1. Direct ownership: booking.affiliate_id == $affiliateId
        // 2. Legacy fallback: booking.affiliate_id IS NULL AND booking.iphone.affiliate_id == $affiliateId
        $query->whereHas('booking', function ($bq) use ($affiliateId) {
            $bq->where(function ($sub) use ($affiliateId) {
                $sub->where('affiliate_id', $affiliateId)
                    ->orWhere(function ($legacy) use ($affiliateId) {
                        $legacy->whereNull('affiliate_id')
                            ->whereHas('iphone', function ($iq) use ($affiliateId) {
                                $iq->where('affiliate_id', $affiliateId);
                            });
                    });
            });
        });

        return $query;
    }

    public function getPaymentList()
    {
        [$start, $end] = $this->getDateRange();

        $query = BookingPayment::query();

        if ($start && $end) {
            $query->whereBetween('paid_at', [$start, $end]);
        }

        $this->applyAffiliateScope($query);

        if (!empty(trim($this->search))) {
            $search = trim($this->search);
            $query->where(function ($sq) use ($search) {
                $sq->whereHas('booking', function ($bq) use ($search) {
                    $bq->where('booking_code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('iphone', function ($iq) use ($search) {
                            $iq->where('name', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%");
                        });
                })->orWhereHas('payment', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%");
                })->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($this->sortField === 'amount') {
            $query->orderBy('amount', $this->sortDirection ?: 'desc');
        } elseif ($this->sortField === 'created' || $this->sortField === 'created_at') {
            $query->orderBy('created_at', $this->sortDirection ?: 'desc');
        } elseif ($this->sortField === 'updated_at') {
            $query->orderBy('updated_at', $this->sortDirection ?: 'desc');
        } else {
            $query->latest('paid_at');
        }

        $this->paymentsList = $query
            ->with([
                'booking.iphone',
                'booking.affiliate',
                'payment',
                'user',
            ])
            ->get();
    }

    public function loadAffiliateRevenue()
    {
        $query = BookingPayment::with([
            'booking.iphone',
            'booking.affiliate',
            'payment',
            'user',
        ]);

        $this->applyAffiliateScope($query);

        $payments = $query
            ->latest('paid_at')
            ->get();

        $this->affiliateRevenue = (float) $payments->sum('amount');

        $this->affiliateBookingCount = $payments
            ->pluck('booking_id')
            ->unique()
            ->count();

        $this->affiliatePayments = $payments;
    }

    public function getAffiliateRevenueToday()
    {
        $today = now('Asia/Jakarta')->toDateString();

        $query = BookingPayment::query()
            ->whereDate('paid_at', $today);

        $this->applyAffiliateScope($query);

        $this->revenueToday = (float) $query->sum('amount');

        $this->bookingToday = (clone $query)
            ->distinct()
            ->count('booking_id');
    }

    public function updatedSearch()
    {
        $this->getPaymentList();
    }

    public function updatedSortField()
    {
        $this->getPaymentList();
    }

    public function updatedSortDirection()
    {
        $this->getPaymentList();
    }

    public function updatedPaginate()
    {
        $this->getPaymentList();
    }

    public function updatedStartDate()
    {
        $this->getPaymentList();
    }

    public function updatedEndDate()
    {
        $this->getPaymentList();
    }

    public function updatedSelectedAll($val)
    {
        if ($val && $this->paymentsList) {
            $this->mySelected = $this->paymentsList->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->mySelected = [];
        }
    }

    public function updatedMySelected()
    {
        if ($this->paymentsList && count($this->mySelected) === $this->paymentsList->count()) {
            $this->selectedAll = true;
        } else {
            $this->selectedAll = false;
        }
    }

    public function destroyAlert($value = '', $onConfirm = 'destroy')
    {
        if (empty($this->mySelected)) {
            return;
        }

        LivewireAlert::title('Hapus Pembayaran?')
            ->warning()
            ->toast()
            ->position('top-end')
            ->withConfirmButton('Hapus')
            ->confirmButtonColor('red')
            ->cancelButtonColor('gray')
            ->withCancelButton('Batal')
            ->onConfirm('destroy')
            ->show();
    }

    public function destroy()
    {
        $user = auth()->user();
        if (!$user || (!$user->can('delete') && !$user->hasRole('super-admin'))) {
            LivewireAlert::title('Akses Ditolak')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
            return;
        }

        if (!empty($this->mySelected)) {
            $query = BookingPayment::whereIn('id', $this->mySelected);
            $this->applyAffiliateScope($query);
            $query->delete();
            $this->mySelected = [];
            $this->selectedAll = false;
            $this->refreshData();

            LivewireAlert::title('Berhasil!')
                ->text('Data pembayaran berhasil dihapus.')
                ->success()
                ->toast()
                ->position('top-end')
                ->show();
        }
    }
}
