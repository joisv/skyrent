<?php

namespace App\Livewire\Reports;

use App\Models\BookingPayment;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

class Revenue extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'paid_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public $revenueToday;
    public $revenueThisMonth;
    public $revenueTotal;
    public $revenueLastMonth;
    public $revenuePenalty;
    public $revenueThisMonthPercentage;

    // getrevenue
    public $singleRevenue;
    public $amount = 0;
    public $created;

    protected function getPaymentBaseQuery()
    {
        $query = BookingPayment::query()->active();
        $user = auth()->user();
        if ($user && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate') || ($user->affiliate_id && !$user->hasRole('super-admin')))) {
            $affiliateId = $user->affiliate_id;
            if ($affiliateId) {
                $query->where('booking_payments.affiliate_id', $affiliateId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        return $query;
    }

    public function mount()
    {
        $today = now()->toDateString();
        $startOfThisMonth = now()->startOfMonth()->toDateString();
        $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();
        $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();

        // Revenue Hari Ini
        $this->revenueToday = $this->getPaymentBaseQuery()->whereDate('paid_at', $today)->sum('amount');

        // Revenue Bulan Ini
        $this->revenueThisMonth = $this->getPaymentBaseQuery()->whereBetween('paid_at', [$startOfThisMonth, now()])->sum('amount');

        // Revenue Bulan Lalu
        $this->revenueLastMonth = $this->getPaymentBaseQuery()->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])->sum('amount');

        // Revenue Total
        $this->revenueTotal = $this->getPaymentBaseQuery()->sum('amount');

        $this->revenuePenalty = $this->getPaymentBaseQuery()->where('type', 'penalty')->sum('amount');

        // Persentase Kenaikan/Penurunan Bulan Ini vs Bulan Lalu
        if ($this->revenueLastMonth > 0) {
            $growth = (($this->revenueThisMonth - $this->revenueLastMonth) / $this->revenueLastMonth) * 100;
            $this->revenueThisMonthPercentage = round($growth, 2);
        } else {
            $this->revenueThisMonthPercentage = $this->revenueThisMonth > 0 ? 100 : 0;
        }
    }

    public function editRev()
    {
        $amount = (int) preg_replace('/[^0-9]/', '', $this->amount);

        if ($this->singleRevenue) {
            $this->singleRevenue->update([
                'amount' => $amount
            ]);
        }

        $this->dispatch('close-modal');
        LivewireAlert::title('Success!')
            ->text('Berhasil merubah pembayaran')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function getData()
    {
        $query = $this->getPaymentBaseQuery()
            ->with(['booking.iphone', 'payment', 'user']);

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('amount', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('booking', function ($bq) use ($search) {
                        $bq->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%")
                            ->orWhere('booking_code', 'like', "%{$search}%")
                            ->orWhereHas('iphone', fn($iq) => $iq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if (in_array($this->sortField, ['created_at', 'updated_at', 'paid_at', 'amount'])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->orderBy('paid_at', 'desc');
        }

        return $query;
    }

    #[On('get-detail')]
    public function getDetailRevenue($id)
    {
        $this->singleRevenue = BookingPayment::with('booking')->findOrFail($id);
        $this->amount = $this->singleRevenue->amount;
        $this->created = $this->singleRevenue->paid_at;
    }

    public function destroy()
    {
        if (auth()->user()->can('delete')) {
            if ($this->mySelected) {
                try {
                    BookingPayment::whereIn('id', $this->mySelected)->update([
                        'is_void' => true,
                        'void_reason' => 'cancelled_from_revenue_report',
                        'voided_by' => auth()->id(),
                        'voided_at' => now(),
                    ]);
                    $this->mySelected = [];
                    $this->selectedAll = false;
                    LivewireAlert::title('Data berhasil di-void')
                        ->position('top-end')
                        ->text('Transaksi berhasil dibatalkan')
                        ->timer(5000)
                        ->toast()
                        ->success()
                        ->show();
                } catch (\Throwable $th) {
                    LivewireAlert::title('Gagal memproses')
                        ->position('top-end')
                        ->text('Tidak dapat membatalkan transaksi')
                        ->timer(5000)
                        ->toast()
                        ->error()
                        ->show();
                }
            } else {
                LivewireAlert::title('Peringatan')
                    ->position('top-end')
                    ->text('Tidak ada data terpilih')
                    ->timer(5000)
                    ->toast()
                    ->error()
                    ->show();
            }
        } else {
            LivewireAlert::title('Kamu tidak memiliki izin')
                ->position('top-end')
                ->text('Tidak dapat membatalkan data')
                ->timer(5000)
                ->toast()
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function delete($data)
    {
        if (auth()->user()->can('delete')) {
            $this->mySelected[] = $data['data'];
            $this->destroy();
        } else {
            LivewireAlert::title('Kamu tidak memiliki izin')
                ->position('top-end')
                ->toast()
                ->text('Tidak dapat membatalkan data')
                ->timer(5000)
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function destroyAlert($value = '', $onConfirm = 'destroy')
    {
        LivewireAlert::title('Batalkan transaksi ini?')
            ->warning()
            ->toast()
            ->position('top-end')
            ->withConfirmButton('Ya, Batalkan')
            ->confirmButtonColor('red')
            ->cancelButtonColor('gray')
            ->withCancelButton('Tutup')
            ->onConfirm($onConfirm, ['data' => $value])
            ->show();
    }

    public function render()
    {
        $query = $this->getData()->paginate($this->paginate);
        return view('livewire.reports.revenue', [
            'revenues' => $query
        ]);
    }
}
