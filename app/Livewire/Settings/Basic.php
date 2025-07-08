<?php

namespace App\Livewire\Settings;

use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class Basic extends Component
{
    public string $site_name;
    public string $logo_cms;
    public string $favicon;
    public string $description;
    public string $logo;
    public string $primary_color = '#000000';
    public string $wichImage;

    #[On('select-poster')]
    public function setSelectedposted($id, $url)
    {
        if ($this->wichImage === 'logo_cms') {
            # code...
            $this->logo_cms = $url;
        } elseif ($this->wichImage === 'logo') {
            $this->logo = $url;
        } else {
            $this->favicon = $url;
        }
    }

    #[On('wich-image')]
    public function setWichImage($props)
    {
        $this->wichImage = $props;
    }

    #[On('remove-img')]
    public function removePoster()
    {
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin')) {
            if ($this->wichImage === 'logo_cms') {
                $this->logo_cms = '';
            } elseif ($this->wichImage === 'logo') {
                $this->logo = '';
            } else {
                $this->favicon = '';
            }
        } else {
            LivewireAlert::title('Booking Gagal')
                ->text('Anda tidak memiliki izin untuk menghapus gambar ini.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
        }
    }

    public function render()
    {
        return view('livewire.settings.basic');
    }
}
