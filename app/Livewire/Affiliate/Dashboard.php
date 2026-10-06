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
        if (!$transfer) {
            LivewireAlert::title('Gagal Menerima iPhone')
                ->position('top-end')
                ->toast()
                ->text('Data transfer tidak ditemukan.')
                ->timer(5000)
                ->error()
                ->show();
            return;
        }

        if ($transfer->status == 'received') {
            LivewireAlert::title('Gagal Menerima iPhone')
                ->position('top-end')
                ->toast()
                ->text('iPhone sudah diterima sebelumnya.')
                ->timer(5000)
                ->error()
                ->show();
            return;
        }

        $user = auth()->user();
        $isAffiliateRole = $user && method_exists($user, 'hasRole') && ($user->hasRole('affiliate-admin') || $user->hasRole('affiliate'));
        if ($isAffiliateRole) {
            if (!$user->affiliate_id || (int) $transfer->to_affiliate_id !== (int) $user->affiliate_id) {
                LivewireAlert::title('Akses Ditolak')
                    ->position('top-end')
                    ->toast()
                    ->text('Anda tidak memiliki hak akses untuk menerima transfer iPhone ini.')
                    ->timer(5000)
                    ->error()
                    ->show();
                return;
            }
        }

        $userId = $user?->id ?? auth()->id();

        $transfer->update([
            'status' => 'received',
            'received_by' => $userId,
            'received_at' => now(),
        ]);

        if ($transfer->iphone) {
            $transfer->iphone->update([
                'affiliate_id' => $transfer->to_affiliate_id,
                'status' => 'ready',
            ]);
        }

        // Auto-resolve duplikat in_transit untuk iPhone yang sama
        IphoneTransfer::where('iphone_id', $transfer->iphone_id)
            ->where('id', '!=', $transfer->id)
            ->whereIn('status', ['in_transit', 'pending'])
            ->update([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
                'notes' => \Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(notes, ''), ' (Auto-resolved duplicate transfer)')"),
            ]);

        $this->mount();

        LivewireAlert::title('Berhasil menerima iPhone')
            ->position('top-end')
            ->toast()
            ->text('iPhone berhasil diterima dan siap disewakan.')
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
        $user = auth()->user();
        if ($user?->affiliate) {
            $this->iphoneTransfers = $user->affiliate
                ->transferIn()
                ->with(['iphone', 'fromAffiliate'])
                ->latest('id')
                ->get();
        } else {
            if ($user && method_exists($user, 'hasRole') && ($user->hasRole('super-admin') || $user->hasRole('admin'))) {
                $this->iphoneTransfers = IphoneTransfer::with(['iphone', 'fromAffiliate'])
                    ->latest('id')
                    ->get();
            } else {
                $this->iphoneTransfers = collect();
            }
        }
    }
}
