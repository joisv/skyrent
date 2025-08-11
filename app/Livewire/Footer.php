<?php

namespace App\Livewire;

use App\Settings\GeneralSettings;
use Livewire\Component;

class Footer extends Component
{
    public $settings;
    
  
    public function render()
    {
        return view('livewire.footer');
    }
}
