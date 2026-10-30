<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AudienceCharts from '@/components/audience/AudienceCharts.vue';
import AudienceSummary from '@/components/audience/AudienceSummary.vue';
import ChannelCards from '@/components/audience/ChannelCards.vue';
import ContactsTable from '@/components/audience/ContactsTable.vue';
import ExportDialog from '@/components/audience/ExportDialog.vue';
import FilterRail from '@/components/audience/FilterRail.vue';
import QuickSegments from '@/components/audience/QuickSegments.vue';
import SaveSegmentDialog from '@/components/audience/SaveSegmentDialog.vue';
import {
    FACET_KEYS,
    FACET_LABELS,
    toQuery,
    useAudienceFilters,
} from '@/composables/useAudienceFilters';
import { usePermissions } from '@/composables/usePermissions';
import { toast } from '@/composables/useToast';
import { index as audienceIndex } from '@/routes/audience';
import { destroy as destroySegment } from '@/routes/segments';
import type {
    ActiveFilterChip,
    AudienceFilterState,
    AudienceOptions,
    AudienceSegment,
    AudienceSummary as Summary,
    ChannelKey,
    ContactRow,
    CursorPage,
    FacetCounts,
} from '@/types';

const props = defineProps<{
    filters: AudienceFilterState;
    contacts: CursorPage<ContactRow>;
    options: AudienceOptions;
    segments: AudienceSegment[];
    staleYear: number;
    hashExportsByDefault: boolean;
    summary?: Summary;
    facets?: FacetCounts;
}>();

const page = usePage();
const { can } = usePermissions();
const audience = useAudienceFilters(() => props.filters);
const filters = audience.filters;

/** Keep the last counts on screen (dimmed) while new deferred counts load. */
const lastSummary = ref<Summary | null>(props.summary ?? null);
const lastFacets = ref<FacetCounts | null>(props.facets ?? null);
watch(
    () => props.summary,
    (value) => value && (lastSummary.value = value),
);
watch(
    () => props.facets,
    (value) => value && (lastFacets.value = value),
);
const summaryLoading = computed(() => props.summary === undefined);
const facetsLoading = computed(() => props.facets === undefined);

const railOpen = ref(false);
const saveOpen = ref(false);

/** Page number for "26–50 of N" (cursor pagination has no offsets, so it rides along in the URL). */
const position = computed(() => {
    const pos = Number(
        new URL(page.url, 'http://localhost').searchParams.get('pos') ?? 1,
    );

    return Number.isInteger(pos) && pos > 0 ? pos : 1;
});

function goToCursor(cursor: string | null, pos: number): void {
    if (!cursor) {
        return;
    }

    router.get(
        audienceIndex().url,
        { filter: toQuery(filters), cursor, pos },
        { preserveState: true, preserveScroll: true, only: ['contacts'] },
    );
}

const labelFor = (facet: (typeof FACET_KEYS)[number], id: number): string =>
    props.options.facets[facet].find((option) => option.id === id)?.label ??
    `#${id}`;

const chips = computed<ActiveFilterChip[]>(() => {
    const list: ActiveFilterChip[] = [];

    for (const facet of FACET_KEYS) {
        for (const id of filters[facet]) {
            list.push({
                key: `${facet}-${id}`,
                label: FACET_LABELS[facet],
                value: labelFor(facet, id),
                remove: () => audience.toggleFacet(facet, id),
            });
        }
    }
    if (filters.search) {
        list.push({
            key: 'search',
            label: 'Search',
            value: filters.search,
            remove: () => audience.setSearch(''),
        });
    }
    const must: [keyof AudienceFilterState, string][] = [
        ['has_mobile', 'Has mobile'],
        ['has_email', 'Has email'],
        ['has_landline', 'Has landline'],
        ['has_arabic_name', 'Arabic name'],
        ['hide_junk', 'No junk names'],
    ];
    for (const [key, label] of must) {
        if (filters[key] === true) {
            list.push({
                key,
                label: 'Must',
                value: label,
                remove: () => audience.setToggle(key as 'has_mobile', false),
            });
        }
    }
    if (!filters.hide_dnc) {
        list.push({
            key: 'hide_dnc',
            label: 'Compliance',
            value: 'Including do-not-contact',
            remove: () => audience.setToggle('hide_dnc', true),
        });
    }
    if (filters.year_from !== null || filters.year_to !== null) {
        list.push({
            key: 'years',
            label: 'Years',
            value: `${filters.year_from ?? props.options.years.min}–${filters.year_to ?? props.options.years.max}`,
            remove: () => audience.setYears(null, null),
        });
    }

    return list;
});

function applySegment(segment: AudienceSegment): void {
    audience.applyQuery(segment.filters);
    toast(`Applied: ${segment.name}`);
}

function removeSegment(segment: AudienceSegment): void {
    if (segment.id === undefined) {
        return;
    }

    router.delete(destroySegment(segment.id).url, {
        preserveScroll: true,
        preserveState: true,
    });
}

const exportOpen = ref(false);
const exportChannel = ref<ChannelKey>('email');

function startExport(channel: ChannelKey = 'email'): void {
    exportChannel.value = channel;
    exportOpen.value = true;
}
</script>

<template>
    <Head title="Audience" />

    <div
        class="grid grid-cols-[290px_minmax(0,1fr)] items-start gap-[18px] max-[900px]:grid-cols-1"
    >
        <FilterRail
            :filters="filters"
            :options="options"
            :facet-counts="lastFacets"
            :open="railOpen"
            :class="{
                'opacity-70 transition-opacity': facetsLoading && lastFacets,
            }"
            @search="audience.setSearch"
            @toggle="audience.setToggle"
            @years="audience.setYears"
            @facet="audience.toggleFacet"
            @clear-facet="audience.clearFacet"
            @reset="audience.reset"
            @close="railOpen = false"
        />

        <div class="flex min-w-0 flex-col gap-[18px]">
            <AudienceSummary
                :summary="lastSummary"
                :chips="chips"
                :class="{
                    'opacity-70 transition-opacity':
                        summaryLoading && lastSummary,
                }"
            />

            <QuickSegments
                :segments="segments"
                :can-manage="can('segments.manage')"
                @apply="applySegment"
                @remove="removeSegment"
                @save="saveOpen = true"
            />

            <div>
                <div
                    class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3"
                >
                    <h2 class="m-0 font-display text-base font-semibold">
                        Channel readiness
                    </h2>
                    <p class="m-0 text-[12.5px] text-subtle">
                        How many of the current audience each channel can
                        actually reach.
                    </p>
                </div>
                <ChannelCards
                    :counts="lastSummary?.channels ?? null"
                    :can-export="can('exports.create')"
                    :class="{
                        'opacity-70 transition-opacity':
                            summaryLoading && lastSummary,
                    }"
                    @export="startExport"
                />
            </div>

            <AudienceCharts
                :filters="filters"
                :options="options"
                :facet-counts="lastFacets"
                :summary="lastSummary"
                :stale-year="staleYear"
                :class="{
                    'opacity-70 transition-opacity':
                        (facetsLoading && lastFacets) ||
                        (summaryLoading && lastSummary),
                }"
                @toggle="audience.toggleFacet"
            />

            <ContactsTable
                :contacts="contacts.data"
                :total="
                    lastSummary && !summaryLoading ? lastSummary.total : null
                "
                :position="position"
                :per-page="contacts.meta.per_page"
                :has-previous="contacts.meta.prev_cursor !== null"
                :has-next="contacts.meta.next_cursor !== null"
                :can-export="can('exports.create')"
                @previous="goToCursor(contacts.meta.prev_cursor, position - 1)"
                @next="goToCursor(contacts.meta.next_cursor, position + 1)"
                @open-filters="railOpen = true"
                @export="startExport()"
            />
        </div>
    </div>

    <SaveSegmentDialog v-model:open="saveOpen" :filters="toQuery(filters)" />
    <ExportDialog
        v-if="can('exports.create')"
        v-model:open="exportOpen"
        :channel="exportChannel"
        :filters="toQuery(filters)"
        :channel-counts="lastSummary?.channels ?? null"
        :hash-by-default="hashExportsByDefault"
    />
</template>
