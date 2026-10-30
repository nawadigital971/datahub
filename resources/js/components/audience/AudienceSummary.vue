<script setup lang="ts">
import Chip from '@/components/Chip.vue';
import type { ActiveFilterChip, AudienceSummary } from '@/types';

const props = defineProps<{
    summary: AudienceSummary | null;
    chips: ActiveFilterChip[];
}>();

const format = (value: number): string => value.toLocaleString('en-US');

const percent = (value: number): number =>
    props.summary && props.summary.total > 0
        ? Math.round((value / props.summary.total) * 100)
        : 0;

const stats = (summary: AudienceSummary) => [
    { label: 'With a mobile', value: summary.with_mobile },
    { label: 'UAE mobile', value: summary.uae_mobile },
    { label: 'Usable email', value: summary.usable_email },
    { label: 'With a landline', value: summary.with_landline },
];
</script>

<template>
    <div
        class="grid grid-cols-[minmax(0,1.3fr)_repeat(4,minmax(0,1fr))] items-end gap-[18px] rounded-card border border-line bg-surface px-[18px] py-4 max-[1200px]:grid-cols-2"
    >
        <div class="max-[1200px]:col-span-full">
            <div class="text-xs font-medium text-subtle">
                Contacts in this audience
            </div>
            <template v-if="summary">
                <div
                    class="font-display text-[40px] leading-[1.05] font-semibold tracking-[-.01em] num max-[560px]:text-[32px]"
                >
                    {{ format(summary.total) }}
                </div>
                <div class="text-[12.5px] text-subtle">
                    of {{ format(summary.database.contacts) }} unique contacts ·
                    {{ format(summary.database.raw_rows) }} raw rows across
                    {{ summary.database.sources }} sources
                </div>
            </template>
            <template v-else>
                <div
                    class="my-1 h-10 w-44 animate-pulse rounded bg-surface-2"
                ></div>
                <div class="h-4 w-64 animate-pulse rounded bg-surface-2"></div>
            </template>
        </div>

        <template v-if="summary">
            <div v-for="stat in stats(summary)" :key="stat.label">
                <div class="text-xs text-subtle">{{ stat.label }}</div>
                <div class="font-display text-[22px] font-semibold num">
                    {{ format(stat.value) }}
                    <span class="text-[13px] font-normal text-subtle"
                        >{{ percent(stat.value) }}%</span
                    >
                </div>
                <div
                    class="mt-1.5 h-1 overflow-hidden rounded-full bg-surface-2"
                >
                    <i
                        class="block h-full rounded-full bg-ink"
                        :style="{ width: `${percent(stat.value)}%` }"
                    ></i>
                </div>
            </div>
        </template>
        <template v-else>
            <div v-for="n in 4" :key="n">
                <div class="h-3 w-20 animate-pulse rounded bg-surface-2"></div>
                <div
                    class="mt-2 h-6 w-24 animate-pulse rounded bg-surface-2"
                ></div>
                <div
                    class="mt-1.5 h-1 animate-pulse rounded-full bg-surface-2"
                ></div>
            </div>
        </template>

        <div class="col-span-full flex flex-wrap gap-1.5">
            <Chip
                v-for="chip in chips"
                :key="chip.key"
                :label="chip.label"
                :value="chip.value"
                @remove="chip.remove()"
            />
        </div>
    </div>
</template>
