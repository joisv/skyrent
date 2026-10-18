<?php

namespace App\Livewire\Reports;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use Livewire\Component;

class TopDevice extends Component
{
    public $search = '';
    public $sortField = 'amount';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public $totalBookings;
    public $totalDevices;
    public $totalAvailable;
    public $topDevice;
    public $revenueTotal;

    public function getDataDevice()
    {
        $user = auth()->user();
        $isAffiliateScoped = ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin'))));
        $affiliateId = $isAffiliateScoped ? $user->affiliate_id : null;

        // Ambil query dasar dengan eager loading
        $query = Iphones::withCount('bookings')
            ->with(['bookings.paymentTransactions']);

        if ($isAffiliateScoped && $affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        // 🔍 SEARCH
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('bookings', function ($bq) use ($search) {
                        $bq->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
            });
        }

        // Eksekusi query
        $devices = $query->get();

        // 💰 Hitung total revenue keseluruhan dari ledger BookingPayment aktif
        $totalRevenueQuery = BookingPayment::active();
        if ($isAffiliateScoped && $affiliateId) {
            $totalRevenueQuery->where('affiliate_id', $affiliateId);
        }
        $totalRevenue = (float) $totalRevenueQuery->sum('amount');
        $this->revenueTotal = $totalRevenue;

        // 🔄 Map ke data siap tampil
        $devices = $devices->map(function ($device) use ($totalRevenue) {
            $totalPendapatan = (float) $device->bookings->flatMap->paymentTransactions
                ->where('is_void', false)
                ->sum('amount');
            $totalDisewa = $device->bookings->sum('duration');

            return [
                'nama' => $device->name,
                'serial_number' => $device->serial_number,
                'created' => $device->created_at,
                'updated_at' => $device->updated_at,
                'total_disewa' => $totalDisewa,
                'total_pendapatan' => $totalPendapatan,
                'kontribusi' => $totalRevenue > 0
                    ? round(($totalPendapatan / $totalRevenue) * 100, 2)
                    : 0,
            ];
        });

        // 🔽 SORTING (berdasarkan input dropdown)
        if ($this->sortField === 'amount') {
            $devices = $devices->sortBy([
                ['total_pendapatan', $this->sortDirection],
            ])->values();
        } elseif ($this->sortField === 'bookings') {
            $devices = $devices->sortBy([
                ['total_disewa', $this->sortDirection],
            ])->values();
        } elseif (in_array($this->sortField, ['created', 'updated_at'])) {
            $devices = $devices->sortBy([
                [$this->sortField, $this->sortDirection],
            ])->values();
        }

        // Top device berdasarkan kontribusi
        $this->topDevice = $devices->sortByDesc('kontribusi')->first();

        // 📊 Statistik tambahan
        $bookingBase = Booking::query();
        $iphoneBase = Iphones::query();
        if ($isAffiliateScoped && $affiliateId) {
            $bookingBase->where('affiliate_id', $affiliateId);
            $iphoneBase->where('affiliate_id', $affiliateId);
        }

        $this->totalBookings = $bookingBase->count();
        $this->totalDevices = $iphoneBase->count();
        $this->totalAvailable = (clone $iphoneBase)->whereDoesntHave('bookings', function ($q) {
            $q->whereIn('status', ['confirmed', 'rented', 'disewa']);
        })->count();

        return $devices;
    }

    public function render()
    {
        return view('livewire.reports.top-device', [
            'devices' => $this->getDataDevice()
        ]);
    }
}
