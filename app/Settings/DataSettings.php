<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class DataSettings extends Settings
{
    /** Contacts whose data year is older than this are flagged as stale. */
    public int $stale_data_year_threshold;

    /** Whether new exports hash phones/emails (SHA-256) by default. */
    public bool $hash_on_export_default;

    /** Days before generated export files are deleted. */
    public int $export_retention_days;

    /** Days before discarded import batches have their staged rows purged. */
    public int $staged_retention_days;

    /** Country assumed for phone numbers written without a country code (ISO code, e.g. "AE"). */
    public string $default_phone_region;

    public static function group(): string
    {
        return 'data';
    }
}
