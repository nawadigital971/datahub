<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('data.stale_data_year_threshold', 2018);
        $this->migrator->add('data.hash_on_export_default', true);
        $this->migrator->add('data.export_retention_days', 90);
        $this->migrator->add('data.staged_retention_days', 30);
    }
};
