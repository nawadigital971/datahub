<?php

namespace App\Actions\Settings;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\GeneralSettingsData;
use App\Models\Brand;
use App\Queries\Settings\BrandAssetsQuery;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;

class UpdateGeneralSettingsAction
{
    public function __construct(private GeneralSettings $settings, private BustAudienceCacheAction $bustCache) {}

    public function handle(GeneralSettingsData $data): void
    {
        $this->settings->site_name = $data->siteName;
        $this->settings->default_theme = $data->defaultTheme;
        $this->settings->save();

        $brand = Brand::current();
        foreach ($data->remove as $collection) {
            $brand->clearMediaCollection($collection);
        }
        foreach ($data->uploads as $collection => $file) {
            $brand->addMedia($file)->toMediaCollection($collection);
        }

        Cache::forget(BrandAssetsQuery::CACHE_KEY);
        $this->bustCache->handle();
    }
}
