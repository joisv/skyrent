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
        $this->migrator->add('general.primary_color', '#350B75');
    }
};
