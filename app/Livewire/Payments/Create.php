<?php

namespace App\Livewire\Payments;

use Livewire\Component;

class Create extends Component
{

    public $name;
    public $slug;
    public $icon;
    public $description;
    public $is_active = true; 
    
    public function render()
    {
        return view('livewire.payments.create');
    }
}
