<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;

/**
 * Human-readable summary of a filter set, e.g. ["Emirate: Dubai, Sharjah", "Must: Has email"].
 * Used in the export log so it is clear which audience was exported.
 */
class AudienceFilterDescriber
{
    private const array FACET_LABELS = [
        'source' => 'Source',
        'type' => 'Type',
        'country' => 'Country',
        'region' => 'Emirate',
        'area' => 'Area',
        'sector' => 'Sector',
        'gender' => 'Gender',
        'call_outcome' => 'Call outcome',
    ];

    private const array MUST_LABELS = [
        'has_mobile' => 'Has mobile',
        'has_email' => 'Has email',
        'has_landline' => 'Has landline',
        'has_arabic_name' => 'Arabic name',
        'hide_junk' => 'No junk names',
    ];

    public function __construct(private AudienceLookups $lookups) {}

    /**
     * @return list<string>
     */
    public function handle(AudienceFilters $filters): array
    {
        $parts = [];

        foreach (AudienceFilters::FACETS as $facet) {
            $ids = $filters->facet($facet);
            if ($ids !== []) {
                $labels = array_map(fn (int $id): string => $this->lookups->label($facet, $id) ?? "#{$id}", $ids);
                $parts[] = self::FACET_LABELS[$facet].': '.implode(', ', $labels);
            }
        }

        $must = array_values(array_filter(
            array_map(fn (string $key, string $label): ?string => $filters->toggle($key) ? $label : null, array_keys(self::MUST_LABELS), self::MUST_LABELS),
        ));
        if ($must !== []) {
            $parts[] = 'Must: '.implode(', ', $must);
        }

        if ($filters->search !== null) {
            $parts[] = "Search: “{$filters->search}”";
        }

        if ($filters->yearFrom !== null || $filters->yearTo !== null) {
            $parts[] = 'Years: '.($filters->yearFrom ?? '…').'–'.($filters->yearTo ?? '…');
        }

        return $parts === [] ? ['Everyone (no filters)'] : $parts;
    }
}
