<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Faq;
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
                title: 'Contact Us | ' . $this->setting->site_name,
                description: 'Hubungi Aether Labs untuk pertanyaan, kolaborasi, atau dukungan pelanggan. Kami siap membantu Anda.',
                author: $this->setting->site_name,
                image: url('/assets/contact-preview.jpg'),
                robots: 'index, follow',
            )
        ]);
    }

    public function howtorent()
    {
        return view('how-to-rent', [
            'how_to_rent' => $this->setting->how_to_rent
        ]);
    }

    public function faq()
    {
        $faq = Faq::orderBy('created_at', 'desc')->get();
        return view('user-faq', [
            'faqs' => $faq
        ]);
    }

    public function products()
    {
        return view('products', [
            'overide' => new SEOData(
                title: 'Daftar iPhone | ' . $this->setting->site_name,
                description: 'Temukan berbagai pilihan iPhone untuk disewa harian, mingguan, atau bulanan dengan harga terjangkau di ' . $this->setting->site_name . '.',
                author: $this->setting->site_name,
                robots: 'index, follow',
            )
        ]);
    }

    public function privacy()
    {
        return view('privacy-policy', [
            'privacy_policy' => $this->setting->privacy_policy,
            'overide' => new SEOData(
                title: 'Kebijakan Privasi | ' . $this->setting->site_name,
                description: 'Baca Kebijakan Privasi ' . $this->setting->site_name . ' untuk mengetahui bagaimana kami mengumpulkan, menggunakan, dan melindungi data pribadi Anda saat menggunakan layanan sewa iPhone.',
                author: $this->setting->site_name,
                robots: 'index, follow',
            ),
        ]);
    }
    
    public function terms()
    {
        return view('terms-conditions', [
            'terms_conditions' => $this->setting->terms_conditions,
            'overide' => new SEOData(
                title: 'Syarat & Ketentuan | ' . $this->setting->site_name,
                description: 'Baca Syarat & Ketentuan ' . $this->setting->site_name . ' untuk memahami aturan penggunaan layanan sewa iPhone, termasuk hak dan kewajiban pengguna serta ketentuan yang berlaku.',
                author: $this->setting->site_name,
                robots: 'index, follow',
            ),
        ]);
    }

    public function bookingStatus()
    {
        return view('booking-status', [
            'overide' => new SEOData(
                title: 'Cek Status Booking | ' . $this->setting->site_name,
                description: 'Cek status booking iPhone Anda di ' . $this->setting->site_name . '. Pastikan informasi pemesanan Anda akurat untuk mendapatkan update terkini.',
            ),
        ]);
    }
}
