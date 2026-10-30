<?php

namespace App\Queries\Imports;

use App\Enums\StagedRowAction;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\Region;
use App\Settings\DataSettings;
use Illuminate\Support\Facades\DB;

/**
 * What committing a batch will add to the reference lists: countries, emirates / regions, areas and
 * sectors its rows name that do not exist yet (rows set to skip are left out).
 *
 * @phpstan-type NewItem array{kind: 'country'|'region'|'area'|'sector', name: string, parent: string|null, rows: int}
 */
class BatchNewReferenceItemsQuery
{
    public function __construct(private WorldCountries $world, private DataSettings $settings) {}

    /**
     * @return list<NewItem>
     */
    public function handle(ImportBatch $batch): array
    {
        $rows = fn () => DB::table('staged_rows')->where('import_batch_id', $batch->id)->where('action', '!=', StagedRowAction::Skip->value);
        $countryName = fn (?string $iso): ?string => $iso === null ? null : (Country::query()->where('iso2', $iso)->first()->name ?? $this->world->attributes($iso)['name']['en'] ?? $iso);
        $countryById = fn (mixed $id): ?string => $id === null ? null : Country::query()->find((int) $id)?->name;
        $items = [];

        $isoCounts = [];
        foreach (['country_iso' => 'country_id', 'nationality_iso' => 'nationality_country_id'] as $isoColumn => $idColumn) {
            foreach ($rows()->whereNull($idColumn)->whereNotNull($isoColumn)->selectRaw("{$isoColumn} AS iso, count(*) AS total")->groupBy($isoColumn)->get() as $row) {
                $isoCounts[(string) $row->iso] = ($isoCounts[(string) $row->iso] ?? 0) + (int) $row->total;
            }
        }
        foreach ($isoCounts as $iso => $total) {
            if (! Country::query()->where('iso2', $iso)->exists()) {
                $items[] = ['kind' => 'country', 'name' => (string) $countryName($iso), 'parent' => null, 'rows' => $total];
            }
        }

        $defaultCountry = $countryName($this->settings->default_phone_region);
        foreach ($rows()->whereNull('region_id')->whereNotNull('region_name')->selectRaw('region_name, country_id, country_iso, count(*) AS total')->groupBy('region_name', 'country_id', 'country_iso')->orderByDesc('total')->get() as $row) {
            $items[] = ['kind' => 'region', 'name' => (string) $row->region_name, 'parent' => $countryById($row->country_id) ?? $countryName($row->country_iso) ?? $defaultCountry, 'rows' => (int) $row->total];
        }

        foreach ($rows()->whereNull('area_id')->whereNotNull('area_name')->where(fn ($query) => $query->whereNotNull('region_id')->orWhereNotNull('region_name'))
            ->selectRaw('area_name, region_id, region_name, count(*) AS total')->groupBy('area_name', 'region_id', 'region_name')->orderByDesc('total')->get() as $row) {
            $parent = $row->region_id !== null ? Region::query()->find((int) $row->region_id)?->name : (string) $row->region_name;
            $items[] = ['kind' => 'area', 'name' => (string) $row->area_name, 'parent' => $parent, 'rows' => (int) $row->total];
        }

        foreach ($rows()->whereNull('sector_id')->whereNotNull('sector_name')->selectRaw('sector_name, count(*) AS total')->groupBy('sector_name')->orderByDesc('total')->get() as $row) {
            $items[] = ['kind' => 'sector', 'name' => (string) $row->sector_name, 'parent' => null, 'rows' => (int) $row->total];
        }

        return $items;
    }
}
