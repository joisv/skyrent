<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{

    public ?string $site_name;
    public ?string $description;
    public ?string $logo_cms;
    public ?string $logo;
    public ?string $favicon;
    public string $primary_color;

    public static function group(): string
    {
        return 'general';
    }
}
