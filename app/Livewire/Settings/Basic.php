<?php

namespace App\Livewire\Settings;

use App\Settings\GeneralSettings;
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
    public string $about_us;
    public string $tagline;
    public string $how_to_rent;
    public string $terms_conditions;
    public string $privacy_policy;
    public string $youtube;
    public string $tiktok;
    public string $instagram;
    public string $facebook;
    public string $twitter;
    public string $whatsapp;
    public string $telegram;


    public function mount(GeneralSettings $settings)
    {
        $this->site_name = $settings->site_name;
        $this->primary_color = $settings->primary_color;
        $this->logo_cms = $settings->logo_cms;
        $this->logo = $settings->logo;
        $this->favicon = $settings->favicon;
        $this->description = $settings->description;
        $this->about_us = $settings->about_us;
        $this->tagline = $settings->tagline;
        $this->how_to_rent = $settings->how_to_rent;
        $this->terms_conditions = $settings->terms_conditions;
        $this->privacy_policy = $settings->privacy_policy;
        $this->youtube = $settings->youtube;
        $this->tiktok = $settings->tiktok;
        $this->instagram = $settings->instagram;
        $this->facebook = $settings->facebook;
        $this->twitter = $settings->twitter;
        $this->whatsapp = $settings->whatsapp;
        $this->telegram = $settings->telegram;  
    }

    public function update()
    {
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin')) {
            $this->validate([
                'logo_cms' => 'nullable|string',
                'logo' => 'nullable|string',
                'favicon' => 'nullable|string',
                'description' => 'nullable|string',
                'site_name' => 'nullable|string',
                'primary_color' => 'required|string',
                'about_us' => 'nullable|string',
            ]);

            $settings = new GeneralSettings();

            $settings->site_name = $this->site_name;
            $settings->description = $this->description;
            $settings->logo_cms = $this->logo_cms;
            $settings->logo = $this->logo;
            $settings->favicon = $this->favicon;
            $settings->primary_color = $this->primary_color;
            $settings->about_us = $this->about_us;

            $settings->save();

            LivewireAlert::title('Setelan berhasil diperbarui')
                ->text('Setelan website kamu telah berhasil diperbarui.')
                ->success()
                ->toast()
                ->position('top-end')
                ->show();
        } else {
            LivewireAlert::title('Kamu tidak memiliki izin')
                ->text('Kamu tidak memiliki izin untuk mengubah setelan ini.')
                ->error()
                ->toast()
                ->position('top-end')
                ->show();
        }
    }

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
