<?php

namespace App\Livewire\Settings;

use App\Settings\GeneralSettings;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class Info extends Component
{

    public $about_us;
    public $contact;

    public function mount(GeneralSettings $settings)
    {
        $this->about_us = $settings->about_us;
    }

    public function update()
    {
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin')) {
            # code...
            $generalSettings = new GeneralSettings();
            $generalSettings->about_us = $this->about_us;
    
            $generalSettings->save();
            $this->reset(['about_us']);
            LivewireAlert::title('Success!')
                ->text('Booking berhasil disimpan.')
                ->success()
                ->toast()
                ->position('top-end')
                ->show();
        } else {
            LivewireAlert::title('Access Denied')
                ->text('kamu tidak memiliki izin untuk mengubah informasi ini.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
        }
    }

    public function render()
    {
        return view('livewire.settings.info');
    }
}
