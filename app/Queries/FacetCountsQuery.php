<?php

namespace App\Queries;

use App\DTOs\AudienceFilters;
use App\Queries\Audience\AudienceCache;
use App\Queries\Audience\AudienceFilterApplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Facet counts for the Audience filter rail and charts.
 *
 * - facets: each facet counted with every OTHER filter applied (so its options stay selectable).
 * - charts: every dimension counted with ALL filters applied (what the bars show).
 * - years:  contacts per last-seen year with all filters (the data-age chart).
 *
 * Default filters read the facet_counts materialized view. Otherwise one GROUPING SETS scan covers
 * all single-valued dimensions, one unnest scan covers sources, and each facet with a selection gets
 * its own "everything except me" query. Results are cached in Redis by filter hash.
 *
 * @phpstan-type Counts array<int, int>
 * @phpstan-type FacetCounts array{facets: array<string, Counts>, charts: array<string, Counts>, years: Counts}
 */
class FacetCountsQuery
{
    /** Facet name => expression on contacts (0 = unknown), matching the facet_counts view. */
    private const array DIMENSIONS = [
        'type' => 'type::int',
        'country' => 'coalesce(country_id, 0)::int',
        'region' => 'coalesce(region_id, 0)::int',
        'area' => 'coalesce(area_id, 0)',
        'sector' => 'coalesce(sector_id, 0)::int',
        'gender' => "CASE gender WHEN 'm' THEN 1 WHEN 'f' THEN 2 ELSE 0 END",
        'call_outcome' => 'coalesce(last_call_outcome, 0)::int',
        'years' => 'coalesce(last_seen_year, 0)::int',
    ];

    public function __construct(private AudienceFilterApplier $applier, private AudienceCache $cache) {}

    /**
     * @return FacetCounts
     */
    public function handle(AudienceFilters $filters): array
    {
        return $this->cache->remember('facets', $filters->hash(), fn (): array => $filters->isDefault()
            ? $this->fromMaterializedView()
            : $this->compute($filters));
    }

    /**
     * @return FacetCounts
     */
    private function fromMaterializedView(): array
    {
        $dimensions = [...AudienceFilters::FACETS, 'last_seen_year'];
        $counts = array_fill_keys($dimensions, []);

        foreach (DB::table('facet_counts')->whereIn('dimension', $dimensions)->get() as $row) {
            $counts[(string) $row->dimension][(int) $row->value_id] = (int) $row->total;
        }

        $years = $counts['last_seen_year'];
        unset($counts['last_seen_year']);

        return ['facets' => $counts, 'charts' => $counts, 'years' => $years];
    }

    /**
     * @return FacetCounts
     */
    private function compute(AudienceFilters $filters): array
    {
        $charts = $this->groupingSets($filters);
        $charts['source'] = $this->sourceCounts($filters);

        $years = $charts['years'];
        unset($charts['years']);

        $facets = $charts;
        foreach (AudienceFilters::FACETS as $facet) {
            if ($filters->facet($facet) !== []) {
                $facets[$facet] = $facet === 'source'
                    ? $this->sourceCounts($filters, except: 'source')
                    : $this->singleDimension($filters, $facet);
            }
        }

        return ['facets' => $facets, 'charts' => $charts, 'years' => $years];
    }

    /**
     * All single-valued dimensions in one pass over the filtered contacts.
     *
     * @return array<string, array<int, int>>
     */
    private function groupingSets(AudienceFilters $filters): array
    {
        $columns = [];
        $labels = [];
        foreach (self::DIMENSIONS as $name => $expression) {
            $columns[] = "{$expression} AS d_{$name}";
            $labels[] = "WHEN GROUPING(d_{$name}) = 0 THEN '{$name}'";
        }

        $inner = $this->applier->apply(DB::table('contacts')->selectRaw(implode(', ', $columns)), $filters);
        $dimensionColumns = array_map(fn (string $name): string => "d_{$name}", array_keys(self::DIMENSIONS));

        $rows = DB::query()
            ->fromSub($inner, 't')
            ->selectRaw('CASE '.implode(' ', $labels).' END AS dimension')
            ->selectRaw('coalesce('.implode(', ', $dimensionColumns).') AS value_id')
            ->selectRaw('count(*) AS total')
            ->groupByRaw('GROUPING SETS ('.implode(', ', array_map(fn (string $column): string => "({$column})", $dimensionColumns)).')')
            ->get();

        $counts = array_fill_keys(array_keys(self::DIMENSIONS), []);
        foreach ($rows as $row) {
            $counts[(string) $row->dimension][(int) $row->value_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function singleDimension(AudienceFilters $filters, string $facet): array
    {
        $expression = self::DIMENSIONS[$facet];

        return $this->pairs(
            $this->applier->apply(DB::table('contacts'), $filters, exceptFacet: $facet)
                ->selectRaw("{$expression} AS value_id, count(*) AS total")
                ->groupByRaw($expression),
        );
    }

    /**
     * Contacts per source (a contact with several sources counts once in each).
     *
     * @return array<int, int>
     */
    private function sourceCounts(AudienceFilters $filters, ?string $except = null): array
    {
        $query = DB::table('contacts')
            ->crossJoin(DB::raw('LATERAL unnest(contacts.source_ids) AS s(source_id)'))
            ->selectRaw('s.source_id::int AS value_id, count(*) AS total')
            ->groupBy('s.source_id');

        return $this->pairs($this->applier->apply($query, $filters, exceptFacet: $except));
    }

    /**
     * @return array<int, int>
     */
    private function pairs(Builder $query): array
    {
        $counts = [];
        foreach ($query->get() as $row) {
            $counts[(int) $row->value_id] = (int) $row->total;
        }

        return $counts;
    }
}
