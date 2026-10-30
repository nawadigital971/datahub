<?php

use App\Enums\Theme;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', ['en' => 'NAWAA Data Hub', 'ar' => 'نواة داتا هب']);
        $this->migrator->add('general.default_theme', Theme::Light->value);
    }
};
