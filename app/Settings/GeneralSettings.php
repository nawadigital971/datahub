<?php

namespace App\Settings;

use App\Enums\Theme;
use Spatie\LaravelSettings\Settings;

/**
 * Logos (light/dark) and the favicon are files, so they are stored through Media Library
 * when the Settings page is built, not as paths here.
 */
class GeneralSettings extends Settings
{
    /** @var array<string, string> Site name keyed by locale, e.g. ['en' => '…', 'ar' => '…']. */
    public array $site_name;

    public Theme $default_theme;

    public static function group(): string
    {
        return 'general';
    }

    /**
     * Site name in the given locale, falling back to the app's fallback locale.
     */
    public function siteName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->site_name[$locale]
            ?? $this->site_name[config('app.fallback_locale')]
            ?? (string) config('app.name');
    }
}
