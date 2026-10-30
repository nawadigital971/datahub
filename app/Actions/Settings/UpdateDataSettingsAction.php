<?php

namespace App\Actions\Settings;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\DataSettingsData;
use App\Settings\DataSettings;

class UpdateDataSettingsAction
{
    public function __construct(private DataSettings $settings, private BustAudienceCacheAction $bustCache) {}

    public function handle(DataSettingsData $data): void
    {
        $this->settings->stale_data_year_threshold = $data->staleDataYearThreshold;
        $this->settings->hash_on_export_default = $data->hashOnExportDefault;
        $this->settings->export_retention_days = $data->exportRetentionDays;
        $this->settings->staged_retention_days = $data->stagedRetentionDays;
        $this->settings->default_phone_region = $data->defaultPhoneRegion;
        $this->settings->save();

        $this->bustCache->handle();
    }
}
