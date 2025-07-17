<?php

namespace App\Livewire\Settings;

use App\Settings\GeneralSettings;
use Livewire\Component;

class Info extends Component
{

    public $about_us;
    public $contact;
    
    public function mount(GeneralSettings $settings)
    {
        $this->about_us = $settings->about_us;
    }

    public function render()
    {
        return view('livewire.settings.info');
    }
}
