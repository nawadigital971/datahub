<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\UpdateDataSettingsAction;
use App\Actions\Settings\UpdateGeneralSettingsAction;
use App\DTOs\DataSettingsData;
use App\DTOs\GeneralSettingsData;
use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowSettingsRequest;
use App\Http\Requests\Admin\UpdateDataSettingsRequest;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Models\Country;
use App\Queries\Settings\BrandAssetsQuery;
use App\Settings\DataSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(ShowSettingsRequest $request, GeneralSettings $general, DataSettings $data, BrandAssetsQuery $assets): Response
    {
        return Inertia::render('admin/settings/Index', [
            'general' => [
                'site_name' => $general->site_name,
                'default_theme' => $general->default_theme->value,
                'assets' => $assets->handle(),
            ],
            'data' => [
                'stale_data_year_threshold' => $data->stale_data_year_threshold,
                'hash_on_export_default' => $data->hash_on_export_default,
                'export_retention_days' => $data->export_retention_days,
                'staged_retention_days' => $data->staged_retention_days,
                'default_phone_region' => $data->default_phone_region,
            ],
            'countries' => Country::query()->orderBy('iso2')->get()->map(fn (Country $country): array => ['value' => $country->iso2, 'label' => "{$country->name} (+{$country->dial_code})"])->all(),
            'themes' => array_map(fn (Theme $theme): array => ['value' => $theme->value, 'label' => ucfirst($theme->value)], Theme::cases()),
            'locales' => config('app.locales'),
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request, UpdateGeneralSettingsAction $action): RedirectResponse
    {
        $action->handle(GeneralSettingsData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'General settings saved.'])->back();
    }

    public function updateData(UpdateDataSettingsRequest $request, UpdateDataSettingsAction $action): RedirectResponse
    {
        $action->handle(DataSettingsData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Data rules saved.'])->back();
    }
}
