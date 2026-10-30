<?php

namespace App\Http\Requests\Audience;

use App\DTOs\AudienceFilters;

trait ValidatesAudienceFilters
{
    /**
     * Rules for an Audience filter set under the given key ("filter" in the URL, "filters" in a segment).
     *
     * @return array<string, list<string>>
     */
    protected function audienceFilterRules(string $key, bool $required = false): array
    {
        $rules = [
            $key => [$required ? 'present' : 'sometimes', 'array:'.implode(',', AudienceFilters::KEYS)],
            "{$key}.search" => ['nullable', 'string', 'max:'.AudienceFilters::SEARCH_MAX_LENGTH],
            "{$key}.year_from" => ['nullable', 'integer', 'between:1990,2100'],
            "{$key}.year_to" => ['nullable', 'integer', 'between:1990,2100'],
        ];

        foreach (array_keys(AudienceFilters::TOGGLES) as $toggle) {
            $rules["{$key}.{$toggle}"] = ['nullable', 'in:0,1,true,false'];
        }

        foreach (AudienceFilters::FACETS as $facet) {
            $rules["{$key}.{$facet}"] = ['nullable', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/'];
        }

        return $rules;
    }
}
