<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\SlugLookupData;
use App\Models\SourceGroup;
use Illuminate\Support\Facades\DB;

class ImportSourceGroupsAction
{
    /**
     * Add source groups that are missing (by slug); existing ones are never overwritten.
     *
     * @param  list<SlugLookupData>  $groups
     */
    public function handle(array $groups): int
    {
        DB::transaction(function () use ($groups): void {
            foreach ($groups as $group) {
                SourceGroup::query()->firstOrCreate(['slug' => $group->slug], ['name' => $group->name]);
            }
        });

        return count($groups);
    }
}
