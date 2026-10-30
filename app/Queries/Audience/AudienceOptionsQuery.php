<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;
use Illuminate\Support\Facades\DB;

/**
 * The options listed in each facet group of the filter rail (labels only; counts are deferred).
 * Lists only values that exist in the data (from the facet_counts view), plus anything selected.
 *
 * @phpstan-type Option array{id: int, label: string}
 * @phpstan-type Options array{facets: array<string, list<Option>>, years: array{min: int, max: int}}
 */
class AudienceOptionsQuery
{
    public function __construct(private AudienceLookups $lookups, private AudienceCache $cache) {}

    /**
     * @return Options
     */
    public function handle(AudienceFilters $filters): array
    {
        /** @var array{present: array<string, list<int>>, years: array{min: int, max: int}} $data */
        $data = $this->cache->remember('options', app()->getLocale(), fn (): array => $this->presentValues());
        $labels = $this->lookups->all();

        $facets = [];
        foreach (AudienceFilters::FACETS as $facet) {
            $ids = array_values(array_unique([...$data['present'][$facet] ?? [], ...$filters->facet($facet)]));
            $facets[$facet] = array_values(array_filter(array_map(
                fn (int $id): ?array => isset($labels[$facet][$id]) ? ['id' => $id, 'label' => $labels[$facet][$id]] : null,
                $ids,
            )));
        }

        return ['facets' => $facets, 'years' => $data['years']];
    }

    /**
     * @return array{present: array<string, list<int>>, years: array{min: int, max: int}}
     */
    private function presentValues(): array
    {
        $present = [];
        $years = [];

        foreach (DB::table('facet_counts')->whereIn('dimension', [...AudienceFilters::FACETS, 'last_seen_year'])->get(['dimension', 'value_id']) as $row) {
            if ($row->dimension === 'last_seen_year') {
                if ((int) $row->value_id > 0) {
                    $years[] = (int) $row->value_id;
                }

                continue;
            }

            $present[(string) $row->dimension][] = (int) $row->value_id;
        }

        $currentYear = (int) date('Y');

        return [
            'present' => $present,
            'years' => [
                'min' => $years === [] ? $currentYear - 10 : min($years),
                'max' => max($currentYear, $years === [] ? $currentYear : max($years)),
            ],
        ];
    }
}
