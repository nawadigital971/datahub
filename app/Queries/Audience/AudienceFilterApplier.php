<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The single place that turns Audience filters into SQL on the contacts table.
 * Used by the records list (through Spatie QueryBuilder) and by the facet/summary queries.
 *
 * @phpstan-type ContactsQuery QueryBuilder|EloquentBuilder<\App\Models\Contact>
 */
class AudienceFilterApplier
{
    /** Facet filter name => contacts column. */
    public const array FACET_COLUMNS = [
        'type' => 'type',
        'country' => 'country_id',
        'region' => 'region_id',
        'area' => 'area_id',
        'sector' => 'sector_id',
        'gender' => 'gender',
        'call_outcome' => 'last_call_outcome',
    ];

    /** Gender facet ids (match the facet_counts view). */
    public const array GENDER_IDS = [1 => Gender::Male, 2 => Gender::Female];

    public const int SEARCH_MIN_LENGTH = 2;

    /**
     * Apply every filter, optionally leaving one facet out.
     *
     * @template TQuery of QueryBuilder|EloquentBuilder<\App\Models\Contact>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(QueryBuilder|EloquentBuilder $query, AudienceFilters $filters, ?string $exceptFacet = null): QueryBuilder|EloquentBuilder
    {
        $this->applyKey($query, 'search', $filters->search);
        $this->applyKey($query, 'year_from', $filters->yearFrom);
        $this->applyKey($query, 'year_to', $filters->yearTo);

        foreach (array_keys(AudienceFilters::TOGGLES) as $key) {
            $this->applyKey($query, $key, $filters->toggle($key));
        }

        foreach (AudienceFilters::FACETS as $key) {
            if ($key !== $exceptFacet) {
                $this->applyKey($query, $key, $filters->facet($key));
            }
        }

        return $query;
    }

    /**
     * Apply one normalized filter value (see AudienceFilters::normalizeValue()).
     *
     * @param  ContactsQuery  $query
     * @param  string|bool|int|list<int>|null  $value
     */
    public function applyKey(QueryBuilder|EloquentBuilder $query, string $key, string|bool|int|array|null $value): void
    {
        match (true) {
            $key === 'search' => $this->applySearch($query, is_string($value) ? $value : null),
            $key === 'year_from' && is_int($value) => $query->where('last_seen_year', '>=', $value),
            $key === 'year_to' && is_int($value) => $query->where('last_seen_year', '<=', $value),
            $key === 'hide_dnc' && $value === true => $query->where('do_not_contact', false),
            $key === 'hide_junk' && $value === true => $query->where('is_junk', false),
            in_array($key, ['has_mobile', 'has_email', 'has_landline', 'has_arabic_name'], true) && $value === true => $query->where($key, true),
            $key === 'source' && is_array($value) && $value !== [] => $query->whereRaw(
                'source_ids && ?::smallint[]',
                ['{'.implode(',', array_filter($value)).'}'],
            ),
            isset(self::FACET_COLUMNS[$key]) && is_array($value) && $value !== [] => $this->applyFacet($query, self::FACET_COLUMNS[$key], $key === 'gender' ? $this->genderValues($value) : $value),
            default => null,
        };
    }

    /**
     * Search the normalized search_text (names, company, email, phone digits) with the trigram index.
     * Phone-like input is reduced to digits without leading zeros so "050 123 4567" finds +971501234567.
     *
     * @param  ContactsQuery  $query
     */
    private function applySearch(QueryBuilder|EloquentBuilder $query, ?string $search): void
    {
        if ($search === null || mb_strlen($search) < self::SEARCH_MIN_LENGTH) {
            return;
        }

        if (preg_match('/^[\d\s+\-().]+$/', $search) === 1) {
            $digits = ltrim((string) preg_replace('/\D/', '', $search), '0');

            if (strlen($digits) >= self::SEARCH_MIN_LENGTH) {
                $query->whereRaw('search_text LIKE ?', ['%'.$digits.'%']);
            }

            return;
        }

        $query->whereRaw(
            "search_text LIKE '%' || nawaa_normalize_text(?) || '%'",
            [str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search)],
        );
    }

    /**
     * @param  ContactsQuery  $query
     * @param  list<int|string>  $values  0 stands for "unknown" (NULL).
     */
    private function applyFacet(QueryBuilder|EloquentBuilder $query, string $column, array $values): void
    {
        $includesUnknown = in_array(0, $values, true);
        $known = array_values(array_filter($values, fn (int|string $value): bool => $value !== 0));

        $query->where(function (QueryBuilder|EloquentBuilder $where) use ($column, $known, $includesUnknown): void {
            if ($known !== []) {
                $where->whereIn($column, $known);
            }
            if ($includesUnknown) {
                $where->orWhereNull($column);
            }
        });
    }

    /**
     * @param  list<int>  $ids
     * @return list<int|string>
     */
    private function genderValues(array $ids): array
    {
        return array_map(
            fn (int $id): int|string => isset(self::GENDER_IDS[$id]) ? self::GENDER_IDS[$id]->value : 0,
            $ids,
        );
    }
}
