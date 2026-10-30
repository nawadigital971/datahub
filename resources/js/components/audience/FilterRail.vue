<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import FacetGroup from '@/components/audience/FacetGroup.vue';
import Toggle from '@/components/Toggle.vue';
import { FACET_KEYS, FACET_LABELS } from '@/composables/useAudienceFilters';
import type {
    AudienceFilterState,
    AudienceOptions,
    FacetCounts,
    FacetKey,
    SelectOption,
    ToggleKey,
} from '@/types';

const props = defineProps<{
    filters: AudienceFilterState;
    options: AudienceOptions;
    facetCounts: FacetCounts | null;
    open: boolean;
}>();

const emit = defineEmits<{
    search: [value: string];
    toggle: [key: ToggleKey, value: boolean];
    years: [from: number | null, to: number | null];
    facet: [key: FacetKey, id: number];
    clearFacet: [key: FacetKey];
    reset: [];
    close: [];
}>();

const searchText = ref(props.filters.search ?? '');
watch(
    () => props.filters.search,
    (value) => {
        if ((value ?? '') !== searchText.value.trim()) {
            searchText.value = value ?? '';
        }
    },
);

const yearOptions = computed<SelectOption<number>[]>(() => {
    const years: SelectOption<number>[] = [];
    for (let y = props.options.years.min; y <= props.options.years.max; y++) {
        years.push({ value: y, label: String(y) });
    }

    return years;
});

const yearFrom = computed({
    get: () => props.filters.year_from ?? props.options.years.min,
    set: (value: number | null) =>
        emit(
            'years',
            value === props.options.years.min ? null : value,
            props.filters.year_to,
        ),
});

const yearTo = computed({
    get: () => props.filters.year_to ?? props.options.years.max,
    set: (value: number | null) =>
        emit(
            'years',
            props.filters.year_from,
            value === props.options.years.max ? null : value,
        ),
});
</script>

<template>
    <aside
        class="sticky top-[calc(env(safe-area-inset-top,0px)+70px)] max-h-[calc(100vh-90px)] overflow-auto rounded-card border border-line bg-surface max-[900px]:fixed max-[900px]:inset-y-0 max-[900px]:left-0 max-[900px]:z-15 max-[900px]:max-h-none max-[900px]:w-[min(320px,88vw)] max-[900px]:rounded-none max-[900px]:pt-[env(safe-area-inset-top,0px)] max-[900px]:pb-[env(safe-area-inset-bottom,0px)] max-[900px]:transition-transform max-[900px]:duration-200"
        :class="
            open
                ? 'max-[900px]:translate-x-0 max-[900px]:shadow-[0_0_40px_rgba(0,0,0,.3)]'
                : 'max-[900px]:-translate-x-[105%]'
        "
        aria-label="Filters"
    >
        <div
            class="sticky top-0 z-1 flex items-center justify-between border-b border-line bg-surface px-3.5 py-3"
        >
            <h2
                class="m-0 font-display text-[13px] font-semibold tracking-[.04em] uppercase"
            >
                Filters
            </h2>
            <div>
                <button
                    type="button"
                    class="border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-ink"
                    @click="emit('reset')"
                >
                    Reset
                </button>
                <button
                    type="button"
                    class="hidden border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-ink max-[900px]:inline-flex"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>
        </div>

        <div class="border-b border-line px-3.5 py-3">
            <input
                v-model="searchText"
                class="w-full rounded-lg border border-line bg-surface-2 px-2.5 py-2"
                type="search"
                placeholder="Name, company, phone, email…"
                aria-label="Search contacts"
                dir="auto"
                @input="emit('search', searchText)"
            />
        </div>

        <div class="border-b border-line px-3.5 py-3">
            <h3
                class="m-0 mb-2 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
            >
                Compliance
            </h3>
            <Toggle
                :model-value="filters.hide_dnc"
                label="Hide do-not-contact"
                @update:model-value="emit('toggle', 'hide_dnc', $event)"
            />
            <Toggle
                :model-value="filters.hide_junk"
                label="Hide junk / unusable names"
                @update:model-value="emit('toggle', 'hide_junk', $event)"
            />
        </div>

        <div class="border-b border-line px-3.5 py-3">
            <h3
                class="m-0 mb-2 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
            >
                Must have
            </h3>
            <Toggle
                :model-value="filters.has_mobile"
                label="Mobile number"
                @update:model-value="emit('toggle', 'has_mobile', $event)"
            />
            <Toggle
                :model-value="filters.has_email"
                label="Email address"
                @update:model-value="emit('toggle', 'has_email', $event)"
            />
            <Toggle
                :model-value="filters.has_landline"
                label="Landline"
                @update:model-value="emit('toggle', 'has_landline', $event)"
            />
            <Toggle
                :model-value="filters.has_arabic_name"
                label="Arabic name"
                @update:model-value="emit('toggle', 'has_arabic_name', $event)"
            />
        </div>

        <div class="border-b border-line px-3.5 py-3">
            <h3
                class="m-0 mb-2 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
            >
                Data year
            </h3>
            <div class="flex items-center gap-2">
                <AppSelect
                    v-model="yearFrom"
                    :options="yearOptions"
                    :searchable="false"
                    size="sm"
                    aria-label="From year"
                />
                <span>to</span>
                <AppSelect
                    v-model="yearTo"
                    :options="yearOptions"
                    :searchable="false"
                    size="sm"
                    aria-label="To year"
                />
            </div>
        </div>

        <FacetGroup
            v-for="facet in FACET_KEYS"
            :key="facet"
            :label="FACET_LABELS[facet]"
            :options="options.facets[facet]"
            :selected="filters[facet]"
            :counts="facetCounts ? facetCounts.facets[facet] : null"
            @toggle="emit('facet', facet, $event)"
            @clear="emit('clearFacet', facet)"
        />
    </aside>
</template>
