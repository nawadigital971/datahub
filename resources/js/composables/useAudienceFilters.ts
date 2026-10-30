import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';
import { index as audienceIndex } from '@/routes/audience';
import type { AudienceFilterState, FacetKey, ToggleKey } from '@/types';

export const FACET_KEYS: FacetKey[] = [
    'source',
    'type',
    'country',
    'region',
    'area',
    'sector',
    'gender',
    'call_outcome',
];

export const FACET_LABELS: Record<FacetKey, string> = {
    source: 'Source',
    type: 'Record type',
    country: 'Country (from phone)',
    region: 'Emirate',
    area: 'Area / district',
    sector: 'Business sector',
    gender: 'Gender',
    call_outcome: 'Last call outcome',
};

export const TOGGLE_DEFAULTS: Record<ToggleKey, boolean> = {
    hide_dnc: true,
    hide_junk: false,
    has_mobile: false,
    has_email: false,
    has_landline: false,
    has_arabic_name: false,
};

export const DEFAULT_FILTERS: AudienceFilterState = {
    search: null,
    year_from: null,
    year_to: null,
    ...TOGGLE_DEFAULTS,
    source: [],
    type: [],
    country: [],
    region: [],
    area: [],
    sector: [],
    gender: [],
    call_outcome: [],
};

const clone = (filters: AudienceFilterState): AudienceFilterState =>
    JSON.parse(JSON.stringify(filters)) as AudienceFilterState;

/**
 * The URL form of the filters: only values that differ from the defaults
 * (mirrors App\DTOs\AudienceFilters::toQuery()).
 */
export function toQuery(filters: AudienceFilterState): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.search) {
        query.search = filters.search;
    }
    if (filters.year_from !== null) {
        query.year_from = String(filters.year_from);
    }
    if (filters.year_to !== null) {
        query.year_to = String(filters.year_to);
    }
    for (const key of Object.keys(TOGGLE_DEFAULTS) as ToggleKey[]) {
        if (filters[key] !== TOGGLE_DEFAULTS[key]) {
            query[key] = filters[key] ? '1' : '0';
        }
    }
    for (const key of FACET_KEYS) {
        if (filters[key].length > 0) {
            query[key] = [...filters[key]].sort((a, b) => a - b).join(',');
        }
    }

    return query;
}

/**
 * Local filter state for the Audience page. Every change is a page visit with the filters in the
 * URL (first page again); the server re-renders the table and re-sends the deferred counts.
 */
export function useAudienceFilters(initial: () => AudienceFilterState) {
    const filters = reactive<AudienceFilterState>(clone(initial()));

    watch(initial, (next) => Object.assign(filters, clone(next)));

    function visit(extra: Record<string, string | number> = {}): void {
        router.get(
            audienceIndex().url,
            { filter: toQuery(filters), ...extra },
            { preserveState: true, preserveScroll: true },
        );
    }

    const visitDebounced = useDebounceFn(() => visit(), 300);

    function setSearch(value: string): void {
        filters.search = value.trim() === '' ? null : value;
        void visitDebounced();
    }

    function setToggle(key: ToggleKey, value: boolean): void {
        filters[key] = value;
        visit();
    }

    function setYears(from: number | null, to: number | null): void {
        if (from !== null && to !== null && from > to) {
            [from, to] = [to, from];
        }
        filters.year_from = from;
        filters.year_to = to;
        visit();
    }

    function toggleFacet(key: FacetKey, id: number): void {
        const values = filters[key];
        filters[key] = values.includes(id)
            ? values.filter((value) => value !== id)
            : [...values, id];
        visit();
    }

    function clearFacet(key: FacetKey): void {
        filters[key] = [];
        visit();
    }

    function reset(): void {
        Object.assign(filters, clone(DEFAULT_FILTERS));
        visit();
    }

    /** Apply a segment's URL-form filters, replacing everything else. */
    function applyQuery(query: Record<string, string>): void {
        router.get(
            audienceIndex().url,
            { filter: query },
            { preserveState: true, preserveScroll: true },
        );
    }

    return {
        filters,
        visit,
        setSearch,
        setToggle,
        setYears,
        toggleFacet,
        clearFacet,
        reset,
        applyQuery,
    };
}
