<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * The Audience filters, normalized. Built from the URL (`filter[...]`) or from a saved segment.
 * Facet lists hold ids; 0 means "unknown / none" (same convention as the facet_counts view).
 */
final readonly class AudienceFilters
{
    /** Facets shown in the filter rail, keyed by filter name. */
    public const array FACETS = ['source', 'type', 'country', 'region', 'area', 'sector', 'gender', 'call_outcome'];

    /** Toggles with their default value. */
    public const array TOGGLES = [
        'hide_dnc' => true,
        'hide_junk' => false,
        'has_mobile' => false,
        'has_email' => false,
        'has_landline' => false,
        'has_arabic_name' => false,
    ];

    public const array YEARS = ['year_from', 'year_to'];

    /** Every filter name accepted in the URL. */
    public const array KEYS = ['search', ...self::YEARS, 'hide_dnc', 'hide_junk', 'has_mobile', 'has_email', 'has_landline', 'has_arabic_name', ...self::FACETS];

    public const int SEARCH_MAX_LENGTH = 100;

    /**
     * @param  array<string, bool>  $toggles
     * @param  array<string, list<int>>  $facets
     */
    public function __construct(
        public ?string $search = null,
        public array $toggles = self::TOGGLES,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public array $facets = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filters = $request->input('filter', []);

        return self::fromArray(is_array($filters) ? $filters : []);
    }

    /**
     * @param  array<array-key, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        $toggles = [];
        foreach (self::TOGGLES as $key => $default) {
            $toggles[$key] = array_key_exists($key, $filters) ? self::normalizeBool($filters[$key]) : $default;
        }

        $facets = [];
        foreach (self::FACETS as $key) {
            $ids = self::normalizeIds($filters[$key] ?? null);
            if ($ids !== []) {
                $facets[$key] = $ids;
            }
        }

        $yearFrom = self::normalizeYear($filters['year_from'] ?? null);
        $yearTo = self::normalizeYear($filters['year_to'] ?? null);
        if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        return new self(self::normalizeSearch($filters['search'] ?? null), $toggles, $yearFrom, $yearTo, $facets);
    }

    /**
     * Normalize one raw filter value the same way for the DTO and for Spatie QueryBuilder filters.
     *
     * @return string|bool|int|list<int>|null
     */
    public static function normalizeValue(string $key, mixed $value): string|bool|int|array|null
    {
        return match (true) {
            $key === 'search' => self::normalizeSearch($value),
            array_key_exists($key, self::TOGGLES) => self::normalizeBool($value),
            in_array($key, self::YEARS, true) => self::normalizeYear($value),
            in_array($key, self::FACETS, true) => self::normalizeIds($value),
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    public function facet(string $key): array
    {
        return $this->facets[$key] ?? [];
    }

    public function toggle(string $key): bool
    {
        return $this->toggles[$key] ?? self::TOGGLES[$key] ?? false;
    }

    /**
     * True when nothing differs from the defaults (the facet_counts view can answer).
     */
    public function isDefault(): bool
    {
        return $this->toQuery() === [];
    }

    /**
     * Every filter with its current value (what the page shows).
     *
     * @return array{search: string|null, year_from: int|null, year_to: int|null, hide_dnc: bool, hide_junk: bool, has_mobile: bool, has_email: bool, has_landline: bool, has_arabic_name: bool, source: list<int>, type: list<int>, country: list<int>, region: list<int>, area: list<int>, sector: list<int>, gender: list<int>, call_outcome: list<int>}
     */
    public function toArray(): array
    {
        /** @var array{search: string|null, year_from: int|null, year_to: int|null, hide_dnc: bool, hide_junk: bool, has_mobile: bool, has_email: bool, has_landline: bool, has_arabic_name: bool, source: list<int>, type: list<int>, country: list<int>, region: list<int>, area: list<int>, sector: list<int>, gender: list<int>, call_outcome: list<int>} */
        return [
            'search' => $this->search,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            ...$this->toggles,
            ...array_map(fn (string $key): array => $this->facet($key), array_combine(self::FACETS, self::FACETS)),
        ];
    }

    /**
     * Only the values that differ from the defaults, in URL form (used for links, segments and cache keys).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->search !== null) {
            $query['search'] = $this->search;
        }
        if ($this->yearFrom !== null) {
            $query['year_from'] = (string) $this->yearFrom;
        }
        if ($this->yearTo !== null) {
            $query['year_to'] = (string) $this->yearTo;
        }
        foreach (self::TOGGLES as $key => $default) {
            if ($this->toggle($key) !== $default) {
                $query[$key] = $this->toggle($key) ? '1' : '0';
            }
        }
        foreach (self::FACETS as $key) {
            if ($this->facet($key) !== []) {
                $query[$key] = implode(',', $this->facet($key));
            }
        }

        return $query;
    }

    /**
     * Stable key for caching counts of this exact filter set.
     */
    public function hash(): string
    {
        return sha1(serialize($this->toQuery()));
    }

    /**
     * A copy without one facet's selection (to count that facet "with every other filter applied").
     */
    public function withoutFacet(string $key): self
    {
        $facets = $this->facets;
        unset($facets[$key]);

        return new self($this->search, $this->toggles, $this->yearFrom, $this->yearTo, $facets);
    }

    private static function normalizeSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim(mb_substr((string) preg_replace('/\s+/u', ' ', $value), 0, self::SEARCH_MAX_LENGTH));

        return $search === '' ? null : $search;
    }

    private static function normalizeBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function normalizeYear(mixed $value): ?int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($year) && $year >= 1990 && $year <= 2100 ? $year : null;
    }

    /**
     * @return list<int>
     */
    private static function normalizeIds(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', is_scalar($value) ? (string) $value : '');

        $ids = [];
        foreach ($parts as $part) {
            $id = filter_var(is_scalar($part) ? trim((string) $part) : null, FILTER_VALIDATE_INT);
            if (is_int($id) && $id >= 0) {
                $ids[$id] = $id;
            }
        }
        ksort($ids);

        return array_values($ids);
    }
}
