<?php

namespace App\Livewire\Affiliate;

use App\Models\Revenue as ModelsRevenue;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Revenue extends Component
{
    public $revenueToday;
    public $user;
    
    public function render()
    {
        return view('livewire.affiliate.revenue');
    }

    public function mount()
    {
        $this->user = Auth::user();
        
        $today = now()->toDateString();
        $startOfThisMonth = now()->startOfMonth()->toDateString();
        $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();
        $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();

        // Revenue Hari Ini
        $this->revenueToday = ModelsRevenue::whereDate('created_at', $today)->sum('amount');
    }
}
