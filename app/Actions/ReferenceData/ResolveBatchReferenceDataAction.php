<?php

namespace App\Actions\ReferenceData;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Actions\Normalization\NormalizeArabicTextAction;
use App\Enums\StagedRowAction;
use App\Models\Area;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\Region;
use App\Models\Sector;
use App\Queries\Imports\WorldCountries;
use App\Settings\DataSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Before a batch is committed, adds the countries, emirates / regions, areas and sectors its rows name but
 * the lists do not have yet, and points the rows at them. Safe to run again (a resumed commit).
 */
class ResolveBatchReferenceDataAction
{
    /** @var array<string, int> */
    private array $countryIds = [];

    public function __construct(
        private WorldCountries $world,
        private NormalizeArabicTextAction $normalize,
        private DataSettings $settings,
        private BustAudienceCacheAction $bustCache,
    ) {}

    /**
     * @return array{countries: int, regions: int, areas: int, sectors: int} how many were created
     */
    public function handle(ImportBatch $batch): array
    {
        $created = ['countries' => 0, 'regions' => 0, 'areas' => 0, 'sectors' => 0];
        $rows = fn () => DB::table('staged_rows')->where('import_batch_id', $batch->id)->where('action', '!=', StagedRowAction::Skip->value);

        DB::transaction(function () use ($rows, $batch, &$created): void {
            /* countries, from phone numbers and nationalities */
            foreach (['country_iso' => 'country_id', 'nationality_iso' => 'nationality_country_id'] as $isoColumn => $idColumn) {
                foreach ($rows()->whereNull($idColumn)->whereNotNull($isoColumn)->distinct()->pluck($isoColumn) as $iso) {
                    $id = $this->countryId((string) $iso, $created);
                    if ($id !== null) {
                        $rows()->whereNull($idColumn)->where($isoColumn, $iso)->update([$idColumn => $id]);
                    }
                }
            }

            /* emirates / regions, under the row's country (else the default phone country) */
            $defaultCountryId = $this->countryId($this->settings->default_phone_region, $created);
            foreach ($rows()->whereNull('region_id')->whereNotNull('region_name')->select('region_name', 'country_id')->distinct()->get() as $value) {
                $countryId = $value->country_id !== null ? (int) $value->country_id : $defaultCountryId;
                if ($countryId === null) {
                    continue;
                }
                $region = $this->findOrCreate(
                    Region::query()->where('country_id', $countryId),
                    (string) $value->region_name,
                    fn (array $name, string $slug): Region => Region::query()->create(['country_id' => $countryId, 'name' => $name, 'slug' => $slug, 'aliases' => []]),
                    Region::query(),
                    $created['regions'],
                );
                $rows()->whereNull('region_id')->where('region_name', $value->region_name)
                    ->when($value->country_id === null, fn ($query) => $query->whereNull('country_id'), fn ($query) => $query->where('country_id', $value->country_id))
                    ->update(['region_id' => $region->id]);
            }

            /* areas, under the row's emirate */
            foreach ($rows()->whereNull('area_id')->whereNotNull('area_name')->whereNotNull('region_id')->select('area_name', 'region_id')->distinct()->get() as $value) {
                $regionId = (int) $value->region_id;
                $area = $this->findOrCreate(
                    Area::query()->where('region_id', $regionId),
                    (string) $value->area_name,
                    fn (array $name, string $slug): Area => Area::query()->create(['region_id' => $regionId, 'name' => $name, 'slug' => $slug, 'aliases' => []]),
                    Area::query()->where('region_id', $regionId),
                    $created['areas'],
                );
                $rows()->whereNull('area_id')->where('area_name', $value->area_name)->where('region_id', $regionId)->update(['area_id' => $area->id]);
            }

            /* sectors */
            foreach ($rows()->whereNull('sector_id')->whereNotNull('sector_name')->distinct()->pluck('sector_name') as $name) {
                $sector = $this->findOrCreate(
                    Sector::query(),
                    (string) $name,
                    fn (array $translations, string $slug): Sector => Sector::query()->create(['name' => $translations, 'slug' => $slug]),
                    Sector::query(),
                    $created['sectors'],
                );
                $rows()->whereNull('sector_id')->where('sector_name', $name)->update(['sector_id' => $sector->id]);
            }

            $batch->touch();
        });

        if (array_sum($created) > 0) {
            $this->bustCache->handle();
        }

        return $created;
    }

    /**
     * @param  array{countries: int, regions: int, areas: int, sectors: int}  $created
     */
    private function countryId(string $iso, array &$created): ?int
    {
        $iso = strtoupper($iso);
        if (isset($this->countryIds[$iso])) {
            return $this->countryIds[$iso];
        }

        $country = Country::query()->where('iso2', $iso)->first();
        if ($country === null) {
            $attributes = $this->world->attributes($iso);
            if ($attributes === null) {
                return null;
            }
            $country = Country::query()->create([...$attributes, 'aliases' => []]);
            $created['countries']++;
        }

        return $this->countryIds[$iso] = $country->id;
    }

    /**
     * An item in $scope already named $text (any language or alias, e.g. created by an earlier row),
     * or a new one named after it.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scope
     * @param  callable(array<string, string>, string): TModel  $create
     * @param  Builder<TModel>  $slugScope
     * @return TModel
     */
    private function findOrCreate(Builder $scope, string $text, callable $create, Builder $slugScope, int &$counter): Model
    {
        $key = $this->normalize->handle($text);
        foreach ((clone $scope)->get() as $item) {
            $names = method_exists($item, 'getTranslations') ? (array) $item->getTranslations('name') : [];
            $spellings = [...array_values($names), ...(array) ($item->getAttribute('aliases') ?? [])];
            foreach ($spellings as $spelling) {
                if (is_string($spelling) && $this->normalize->handle($spelling) === $key) {
                    return $item;
                }
            }
        }

        $display = $this->displayName($text);
        $isArabic = preg_match('/\p{Arabic}/u', $display) === 1;
        $base = Str::slug($display) ?: 'item';
        $slug = $base;
        for ($n = 2; (clone $slugScope)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        $counter++;

        return $create($isArabic ? ['en' => $display, 'ar' => $display] : ['en' => $display], $slug);
    }

    /**
     * "RIYADH" / "riyadh" → "Riyadh"; mixed case and Arabic stay as written.
     */
    private function displayName(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === mb_strtolower($text) || $text === mb_strtoupper($text) ? mb_convert_case($text, MB_CASE_TITLE) : $text;
    }
}
