<?php

namespace App\Livewire\Affiliate;

use App\Models\IphoneTransfer;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class Dashboard extends Component
{

    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public $iphoneTransfers;

    public function acceptTransferIphone($transferId)
    {
        $transfer = IphoneTransfer::find($transferId);
        if ($transfer->status == 'received') {
            LivewireAlert::title('Gagal Menerima iPhone')
                ->position('top-end')
                ->toast()
                ->text('iPhone sudah diterima')
                ->timer(5000)
                ->error()
                ->show();
            return;
        }
        $transfer->update([
            'status' => 'received',
            'received_by' => auth()->id(),
            'received_at' => now(),
        ]);

        $transfer->iphone->update([
            'affiliate_id' => $transfer->to_affiliate_id,
            'status' => 'ready',
        ]);
        LivewireAlert::title('Berhasil menerima iPhone')
            ->position('top-end')
            ->toast()
            ->text('iPhone berhasil diterima')
            ->timer(5000)
            ->success()
            ->show();
    }
    public function render()
    {
        return view('livewire.affiliate.dashboard');
    }

    // menampilkan data transfer iphone yang sedang berlangsung
    public function mount()
    {
        $this->iphoneTransfers = auth()->user()
            ->affiliate
            ?->transferIn()
            // ->where('status', 'in_transit')
            ->with(['iphone'])
            ->get();
    }
}
