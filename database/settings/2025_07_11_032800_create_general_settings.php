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
        $this->migrator->add('general.about_us', 'halo nama saya jois vanka, sekarang saya merasa tidak fit dan tidak <b>enak kontol&nbsp;</b>');
        $this->migrator->add('general.primary_color', '#350B75');
        $this->migrator->add('general.youtube', '');
        $this->migrator->add('general.tiktok', '');
        $this->migrator->add('general.instagram', '');
        $this->migrator->add('general.facebook', '');
        $this->migrator->add('general.twitter', '');
        $this->migrator->add('general.whatsapp', '');
        $this->migrator->add('general.telegram', '');
        $this->migrator->add('general.tagline', 'Tagline');
        $this->migrator->add('general.how_to_rent', 'Cara Sewa');
        $this->migrator->add('general.terms_conditions', 'Syarat & Ketentuan');
        $this->migrator->add('general.privacy_policy', 'Kebijakan Privasi');
    }
};
