<?php

namespace App\Livewire;

use App\Settings\GeneralSettings;
use Livewire\Component;

class Footer extends Component
{
    public $settings = [];

    public function mount(GeneralSettings $setting)
    {
        // Konversi ke array
         $this->settings = [
            'site_name'       => $setting->site_name,
            'description'     => $setting->description,
            'logo_cms'        => $setting->logo_cms,
            'logo'            => $setting->logo,
            'favicon'         => $setting->favicon,
            'about_us'        => $setting->about_us,
            'tagline'         => $setting->tagline,
            'how_to_rent'     => $setting->how_to_rent,
            'terms_conditions'=> $setting->terms_conditions,
            'privacy_policy'  => $setting->privacy_policy,
            'youtube'         => $setting->youtube,
            'tiktok'          => $setting->tiktok,
            'instagram'       => $setting->instagram,
            'facebook'        => $setting->facebook,
            'twitter'         => $setting->twitter,
            'whatsapp'        => $setting->whatsapp,
            'telegram'        => $setting->telegram,
            'primary_color'   => $setting->primary_color,
        ];
    }

    public function render()
    {
        return view('livewire.footer', [
            'data' => $this->settings
        ]);
    }
}
