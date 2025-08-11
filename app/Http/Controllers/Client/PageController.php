<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Iphones;
use App\Settings\GeneralSettings;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class PageController extends Controller
{
    //

    protected $setting;

    public function __construct(GeneralSettings $generalSetting)
    {
        $this->setting = $generalSetting;
    }

    public function welcome()
    {
        return view('welcome', [
            'settings' => $this->setting
        ]);
    }
    
    public function detail(Iphones $iphones)
    {
        $seo = new SEOData(
            site_name: $iphones->name,
            title: $iphones->name . ' - ' . $this->setting->site_name,
            description: $iphones->description,
            robots: 'index, follow'
        );

        return view('detail', [
            'iphone' => $iphones->load(['gallery', 'bookings']),
            'overide' => $seo
        ]);
    }

    public function contacts()
    {
        return view('contacts', [
            'settings' => $this->setting,
            'overide' => new SEOData(
                title: 'Contact Us | '. $this->setting->site_name,
                description: 'Hubungi Aether Labs untuk pertanyaan, kolaborasi, atau dukungan pelanggan. Kami siap membantu Anda.',
                author: $this->setting->site_name,
                image: url('/assets/contact-preview.jpg'),
                robots: 'index, follow',
            )
        ]);
    }
}
