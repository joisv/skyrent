<?php

namespace App\Livewire\Analytics;

use App\Models\Booking;
use App\Models\BookingPayment;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public $total_revenues;
    public $bookings;

    public function mount()
    {
        $user = auth()->user();
        $isAffiliateScoped = ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin'))));
        $affiliateId = $isAffiliateScoped ? $user->affiliate_id : null;

        $query = BookingPayment::active();
        if ($isAffiliateScoped && $affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        $this->total_revenues = (float) $query->sum('amount');
    }

    public function getRevenues()
    {
        $user = auth()->user();
        $isAffiliateScoped = ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin'))));
        $affiliateId = $isAffiliateScoped ? $user->affiliate_id : null;

        $query = Booking::whereDate('created', Carbon::today())->with('iphone');
        if ($isAffiliateScoped && $affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        $this->bookings = $query->get();
    }

    #[On('update-revenues')]
    public function updateRevenues($property)
    {
        $this->getRevenues();
    }

    public function render()
    {
        return view('livewire.analytics.dashboard', [
            'total_revenues' => $this->total_revenues
        ]);
    }
}
