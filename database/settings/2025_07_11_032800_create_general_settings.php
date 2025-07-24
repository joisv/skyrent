<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'SkyRent');
        $this->migrator->add('general.description', 'Lorem Ipsum Sit Doler Amet');
        $this->migrator->add('general.logo', '');
        $this->migrator->add('general.logo_cms', '');
        $this->migrator->add('general.favicon', '');
        $this->migrator->add('general.about_us', 'About Us');
        $this->migrator->add('general.primary_color', '#350B75');
        $this->migrator->add('general.youtube', 'https://www.youtube.com/channel/UCV6b1a5g2k8z9c4d3e5f7wA');
        $this->migrator->add('general.tiktok', 'tiktok.com/@example');
        $this->migrator->add('general.instagram', 'instagram.com/example');
        $this->migrator->add('general.facebook', 'facebook.com/example');
        $this->migrator->add('general.twitter', 'x.com/example');
        $this->migrator->add('general.whatsapp', 'wa.me/1234567890');
        $this->migrator->add('general.telegram', 'te.me/example');
        $this->migrator->add('general.tagline', 'Tagline');
        $this->migrator->add('general.how_to_rent', 'Cara Sewa');
        $this->migrator->add('general.terms_conditions', 'Syarat & Ketentuan');
        $this->migrator->add('general.privacy_policy', 'Kebijakan Privasi');
    }
};
