<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\CleaningRuleData;
use App\Models\CleaningRule;
use Illuminate\Support\Facades\DB;

class ImportCleaningRulesAction
{
    /**
     * Add the starter cleaning rules that are missing; rules edited or deleted later are left alone
     * (a deleted rule comes back only if it is still in the starter file and the import is run again).
     *
     * @param  list<CleaningRuleData>  $rules
     */
    public function handle(array $rules): int
    {
        $created = 0;

        DB::transaction(function () use ($rules, &$created): void {
            foreach ($rules as $position => $rule) {
                $model = CleaningRule::query()->firstOrCreate(
                    ['type' => $rule->type, 'key' => $rule->key],
                    ['value' => $rule->value, 'notes' => $rule->notes, 'position' => $rule->position ?? $position],
                );
                $created += $model->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $created;
    }
}
