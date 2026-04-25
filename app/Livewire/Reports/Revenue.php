<?php

namespace App\Livewire\Reports;

use App\Models\Revenue as ModelsRevenue;
use Livewire\Attributes\On;
use Livewire\Component;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

class Revenue extends Component
{
    public $search = '';
    public $sortField = 'created_at';
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

    public function mount()
    {
        $today = now()->toDateString();
        $startOfThisMonth = now()->startOfMonth()->toDateString();
        $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();
        $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();

        // Revenue Hari Ini
        $this->revenueToday = ModelsRevenue::whereDate('created_at', $today)->sum('amount');

        // Revenue Bulan Ini
        $this->revenueThisMonth = ModelsRevenue::whereBetween('created_at', [$startOfThisMonth, now()])->sum('amount');

        // Revenue Bulan Lalu
        $this->revenueLastMonth = ModelsRevenue::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('amount');

        // Revenue Total
        $this->revenueTotal = ModelsRevenue::sum('amount');

        $this->revenuePenalty = ModelsRevenue::where('type', 'penalty')->sum('amount');


        // Persentase Kenaikan/Penurunan Bulan Ini vs Bulan Lalu
        if ($this->revenueLastMonth > 0) {
            $growth = (($this->revenueThisMonth - $this->revenueLastMonth) / $this->revenueLastMonth) * 100;
            $this->revenueThisMonthPercentage = round($growth, 2); // + atau - persentase
        } else {
            $this->revenueThisMonthPercentage = $this->revenueThisMonth > 0 ? 100 : 0;
        }
    }


    public function editRev()
    {
        $amount = (int) preg_replace('/[^0-9]/', '', $this->amount);

        $this->singleRevenue->update([
            'amount' => $amount
        ]);
        $this->dispatch('close-modal');
        LivewireAlert::title('Success!')
            ->text('Berhasil merubah pendapatan')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();
    }

    public function getData()
    {
        $query = ModelsRevenue::query()
            ->with(['booking.iphone']);

        if ($this->search) {
            $query->search([
                'amount',
                'booking.name',          // nama pemesan
                'booking.description',   //keterangan booking
                'booking.iphone.model',  //ingin cari berdasarkan model iPhone
                'updated_at'
            ], $this->search);
        }

        if (in_array($this->sortField, ['created_at', 'updated_at'])) {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }

    #[On('get-detail')]
    public function getDetailRevenue($id)
    {
        $this->singleRevenue = ModelsRevenue::where('id', $id)->with('booking')->first();
        $this->amount = $this->singleRevenue->amount;
        $this->created = $this->singleRevenue->created;
    }

    public function destroy()
    {
        if (auth()->user()->can('delete')) {
            if ($this->mySelected) {
                try {
                    //code...
                    // dd('masuk ke try');
                    ModelsRevenue::whereIn('id', $this->mySelected)->delete();
                    $this->mySelected = [];
                    $this->selectedAll = false;
                    LivewireAlert::title('Data berhasil dihapus')
                        ->position('top-end')
                        ->text('bulk delete data berhasil')
                        ->timer(5000)
                        ->toast()
                        ->success()
                        ->show();
                } catch (\Throwable $th) {
                    LivewireAlert::title('iPhone tidak ditemukan 1')
                        ->position('top-end')
                        ->text('tidak dapat menghapus data')
                        ->timer(5000)
                        ->toast()
                        ->error()
                        ->show();
                }
            } else {
                LivewireAlert::title('iPhone tidak ditemukan 2')
                    ->position('top-end')
                    ->text('tidak dapat menghapus data')
                    ->timer(5000)
                    ->toast()
                    ->error()
                    ->show();
            }
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->text('tidak dapat menghapus data')
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
            $this->destroy('deleted successfully');
        } else {
            LivewireAlert::title('kamu tidak memiliki izin')
                ->position('top-end')
                ->text('tidak dapat menghapus data')
                ->timer(5000)
                ->error()
                ->show();
            $this->mySelected = [];
            $this->selectedAll = false;
        }
    }

    public function destroyAlert($value = '', $onConfirm = 'destroy')
    {

        LivewireAlert::title('Delete this posts ?')
            ->warning()
            ->toast()
            ->position('top-end')
            ->withConfirmButton('Delete')
            ->confirmButtonColor('green')
            ->cancelButtonColor('red')
            ->withCancelButton('Cancel')
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
