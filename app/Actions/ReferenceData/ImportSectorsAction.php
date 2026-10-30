<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\SlugLookupData;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;

class ImportSectorsAction
{
    /**
     * Add sectors that are missing (by slug); existing ones are never overwritten.
     *
     * @param  list<SlugLookupData>  $sectors
     */
    public function handle(array $sectors): int
    {
        DB::transaction(function () use ($sectors): void {
            foreach ($sectors as $sector) {
                Sector::query()->firstOrCreate(['slug' => $sector->slug], ['name' => $sector->name]);
            }
        });

        return count($sectors);
    }
}
