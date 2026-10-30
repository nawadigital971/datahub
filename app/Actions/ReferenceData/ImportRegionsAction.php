<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\RegionData;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportRegionsAction
{
    /**
     * Add regions that are missing (by slug); existing ones are never overwritten. Countries must be imported first.
     *
     * @param  list<RegionData>  $regions
     */
    public function handle(array $regions): int
    {
        $countryIds = Country::query()->pluck('id', 'iso2');

        DB::transaction(function () use ($regions, $countryIds): void {
            foreach ($regions as $region) {
                $countryId = $countryIds[$region->countryIso2]
                    ?? throw new InvalidArgumentException("Region {$region->slug}: unknown country \"{$region->countryIso2}\". Import countries first.");

                Region::query()->firstOrCreate(
                    ['slug' => $region->slug],
                    ['country_id' => $countryId, 'name' => $region->name, 'aliases' => $region->aliases],
                );
            }
        });

        return count($regions);
    }
}
