<?php

namespace App\Actions\Exports;

use App\Models\Export;
use App\Settings\DataSettings;

class PurgeExpiredExportFilesAction
{
    public function __construct(private DataSettings $settings) {}

    /**
     * Delete export files older than the retention period. The log entries stay.
     */
    public function handle(): int
    {
        $purged = 0;

        Export::query()
            ->where('created_at', '<', now()->subDays($this->settings->export_retention_days))
            ->whereHas('media')
            ->lazyById(200)
            ->each(function (Export $export) use (&$purged): void {
                $export->clearMediaCollection('file');
                $purged++;
            });

        return $purged;
    }
}
