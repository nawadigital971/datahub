<?php

use App\Enums\MappingLayout;
use App\Models\Area;
use App\Models\CleaningRule;
use App\Models\Country;
use App\Models\MappingTemplate;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Source;
use App\Models\SourceGroup;

/**
 * @return list<array<string, mixed>>
 */
function referenceRows(string $file): array
{
    return json_decode(file_get_contents(database_path("data/{$file}")), true);
}

it('imports every reference dataset from database/data', function () {
    $this->artisan('app:import-all')->assertSuccessful();

    expect(Country::query()->count())->toBe(count(referenceRows('countries.json')))
        ->and(Region::query()->count())->toBe(7)
        ->and(Area::query()->count())->toBe(count(referenceRows('areas.json')))
        ->and(Sector::query()->count())->toBe(count(referenceRows('sectors.json')))
        ->and(SourceGroup::query()->count())->toBe(count(referenceRows('source_groups.json')))
        ->and(Source::query()->count())->toBe(0)
        ->and(MappingTemplate::query()->pluck('layout')->all())
        ->toEqualCanonicalizing([MappingLayout::Master, MappingLayout::FormExport, MappingLayout::BusinessDirectory]);
});

it('links areas to their emirate and keeps Arabic and English aliases', function () {
    $this->artisan('app:import-all')->assertSuccessful();

    $area = Area::query()->where('slug', 'international-city')->sole();

    expect($area->region->slug)->toBe('dubai')
        ->and($area->getTranslation('name', 'ar'))->toBe('المدينة العالمية')
        ->and($area->aliases)->toContain('انترناشيونال سيتي', 'International City');
});

it('does not create duplicates when run again', function () {
    $this->artisan('app:import-all')->assertSuccessful();
    $this->artisan('app:import-all')->assertSuccessful();

    expect(Country::query()->count())->toBe(count(referenceRows('countries.json')))
        ->and(Area::query()->count())->toBe(count(referenceRows('areas.json')))
        ->and(SourceGroup::query()->count())->toBe(count(referenceRows('source_groups.json')));
});

it('keeps names edited in the dashboard when run again', function () {
    $this->artisan('app:import-all')->assertSuccessful();
    Country::query()->where('iso2', 'AE')->sole()->setTranslation('name', 'en', 'Emirates')->save();

    $this->artisan('app:import-countries')->assertSuccessful();

    expect(Country::query()->where('iso2', 'AE')->sole()->getTranslation('name', 'en'))->toBe('Emirates');
});

it('imports the cleaning rules once', function () {
    $this->artisan('app:import-all')->assertSuccessful();
    $this->artisan('app:import-all')->assertSuccessful();

    expect(CleaningRule::query()->count())->toBe(count(referenceRows('cleaning_rules.json')));
});

it('stops with a clear error when a parent dataset was not imported', function () {
    $this->artisan('app:import-regions');
})->throws(InvalidArgumentException::class, 'unknown country "AE". Import countries first.');
