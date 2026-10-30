<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\AreaData;
use App\Models\Area;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportAreasAction
{
    /**
     * Add areas that are missing (by region and slug); existing ones are never overwritten. Regions must be imported first.
     *
     * @param  list<AreaData>  $areas
     */
    public function handle(array $areas): int
    {
        $regionIds = Region::query()->pluck('id', 'slug');

        DB::transaction(function () use ($areas, $regionIds): void {
            foreach ($areas as $area) {
                $regionId = $regionIds[$area->regionSlug]
                    ?? throw new InvalidArgumentException("Area {$area->slug}: unknown region \"{$area->regionSlug}\". Import regions first.");

                Area::query()->firstOrCreate(
                    ['region_id' => $regionId, 'slug' => $area->slug],
                    ['name' => $area->name, 'aliases' => $area->aliases],
                );
            }
        });

        return count($areas);
    }
}
