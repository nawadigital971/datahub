<script setup lang="ts">
import { computed } from 'vue';
import BarChartCard from '@/components/audience/BarChartCard.vue';
import type {
    AudienceFilterState,
    AudienceOptions,
    AudienceSummary,
    ChartBar as Bar,
    FacetCounts,
    FacetKey,
} from '@/types';

const props = defineProps<{
    filters: AudienceFilterState;
    options: AudienceOptions;
    facetCounts: FacetCounts | null;
    summary: AudienceSummary | null;
    staleYear: number;
}>();

defineEmits<{
    toggle: [facet: FacetKey, id: number];
}>();

const format = (value: number): string => value.toLocaleString('en-US');

/** Bars for one dimension (all filters applied), biggest first, unknown (0) left out. */
const barsFor = (facet: FacetKey, limit: number): Bar[] | null => {
    if (!props.facetCounts) {
        return null;
    }

    const counts = props.facetCounts.charts[facet] as Record<string, number>;
    const labels = new Map(
        props.options.facets[facet].map((option) => [option.id, option.label]),
    );

    return Object.entries(counts)
        .map(([id, value]) => ({
            id: Number(id),
            label: labels.get(Number(id)) ?? `#${id}`,
            value,
        }))
        .filter((bar) => bar.id !== 0 && bar.value > 0)
        .sort((a, b) => b.value - a.value)
        .slice(0, limit);
};

/** Two-year buckets from the first data year to this year. */
const ageBuckets = computed(() => {
    if (!props.facetCounts) {
        return null;
    }

    const years = props.facetCounts.years as Record<string, number>;
    const buckets: { label: string; value: number; stale: boolean }[] = [];

    for (
        let start = props.options.years.min;
        start <= props.options.years.max;
        start += 2
    ) {
        const end = start + 1;
        buckets.push({
            label: `${start}–${String(end).slice(-2)}`,
            value: (years[start] ?? 0) + (years[end] ?? 0),
            stale: end < props.staleYear,
        });
    }

    return buckets;
});

const ageMax = computed(() =>
    Math.max(1, ...(ageBuckets.value ?? []).map((bucket) => bucket.value)),
);

const completeness = computed(() => {
    const summary = props.summary;
    if (!summary) {
        return null;
    }

    const percent = (value: number): number =>
        summary.total > 0 ? Math.round((value / summary.total) * 100) : 0;

    return [
        ['Mobile', summary.completeness.mobile],
        ['Email', summary.completeness.email],
        ['Arabic name', summary.completeness.arabic_name],
        ['English name', summary.completeness.english_name],
        ['Gender', summary.completeness.gender],
        ['Area / district', summary.completeness.area],
    ].map(([label, value]) => ({
        label: label as string,
        percent: percent(value as number),
    }));
});
</script>

<template>
    <div class="grid grid-cols-2 gap-3.5 max-[900px]:grid-cols-1">
        <BarChartCard
            title="By source"
            subtitle="Click a bar to filter"
            :bars="barsFor('source', 10)"
            :selected="filters.source"
            @toggle="$emit('toggle', 'source', $event)"
        />
        <BarChartCard
            title="By emirate"
            subtitle="UAE contacts by emirate"
            :bars="barsFor('region', 7)"
            :selected="filters.region"
            @toggle="$emit('toggle', 'region', $event)"
        />
        <BarChartCard
            title="By country"
            subtitle="Country of the phone number"
            :bars="barsFor('country', 8)"
            :selected="filters.country"
            @toggle="$emit('toggle', 'country', $event)"
        />

        <div
            class="min-w-0 rounded-card border border-line bg-surface px-4 py-3.5"
        >
            <h3 class="m-0 mb-0.5 text-[13.5px] font-semibold">Data age</h3>
            <p class="m-0 mb-2.5 text-xs text-subtle">
                Year the record was last seen. Older numbers churn more.
            </p>
            <template v-if="ageBuckets">
                <div
                    class="relative flex h-[150px] items-end gap-1.5 border-b border-line pt-3.5"
                >
                    <div
                        v-for="bucket in ageBuckets"
                        :key="bucket.label"
                        class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
                    >
                        <span class="font-mono text-[10.5px] text-subtle num">{{
                            bucket.value ? format(bucket.value) : ''
                        }}</span>
                        <div
                            class="min-h-px w-full rounded-t-[3px]"
                            :class="bucket.stale ? 'bg-bar-2' : 'bg-bar'"
                            :style="{
                                height: `${((bucket.value / ageMax) * 100).toFixed(1)}%`,
                            }"
                        ></div>
                    </div>
                </div>
                <div class="mt-[5px] flex gap-1.5">
                    <span
                        v-for="bucket in ageBuckets"
                        :key="bucket.label"
                        class="flex-1 text-center font-mono text-[11px] text-subtle"
                        >{{ bucket.label }}</span
                    >
                </div>
            </template>
            <div
                v-else
                class="h-[170px] animate-pulse rounded bg-surface-2"
                aria-hidden="true"
            ></div>
        </div>

        <BarChartCard
            title="Top areas"
            subtitle="Districts inside the selected emirates"
            :bars="barsFor('area', 8)"
            :selected="filters.area"
            @toggle="$emit('toggle', 'area', $event)"
        />

        <div
            class="min-w-0 rounded-card border border-line bg-surface px-4 py-3.5"
        >
            <h3 class="m-0 mb-0.5 text-[13.5px] font-semibold">
                Field completeness
            </h3>
            <p class="m-0 mb-2.5 text-xs text-subtle">
                Share of this audience with each field filled
            </p>
            <div
                v-if="completeness"
                class="grid grid-cols-2 gap-x-[18px] gap-y-2.5 max-[560px]:grid-cols-1"
            >
                <div
                    v-for="field in completeness"
                    :key="field.label"
                    class="text-[12.5px]"
                >
                    <div class="flex justify-between">
                        <span>{{ field.label }}</span>
                        <span class="font-mono text-subtle num"
                            >{{ field.percent }}%</span
                        >
                    </div>
                    <div
                        class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-2"
                    >
                        <i
                            class="block h-full"
                            :class="field.percent < 40 ? 'bg-warn' : 'bg-bar'"
                            :style="{ width: `${field.percent}%` }"
                        ></i>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="h-[110px] animate-pulse rounded bg-surface-2"
                aria-hidden="true"
            ></div>
        </div>
    </div>
</template>
