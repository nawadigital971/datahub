<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as audienceIndex } from '@/routes/audience';

type Issue = {
    key: string;
    severity: 'high' | 'med' | 'low';
    title: string;
    description: string;
    rule: string;
    count: number;
    audience: Record<string, string> | null;
};

defineProps<{
    health?: { total: number; issues: Issue[] };
}>();

const STRIPE = { high: 'bg-bad', med: 'bg-warn', low: 'bg-good' } as const;
const format = (value: number): string => value.toLocaleString('en-US');
const share = (count: number, total: number): string =>
    total > 0
        ? `${((count / total) * 100).toFixed(count / total < 0.01 ? 2 : 1)}%`
        : '0%';
</script>

<template>
    <Head title="Data health" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Data health</h2>
        <p v-if="health" class="m-0 text-[12.5px] text-subtle num">
            Across {{ format(health.total) }} contacts · updated after every
            import
        </p>
    </div>
    <p class="m-0 mb-4 max-w-[80ch] text-subtle">
        Live counts of the problems the import rules look for. New files are
        checked row by row before they reach the contacts; these numbers show
        what is still in the data.
    </p>

    <div v-if="health" class="grid grid-cols-2 gap-3.5 max-[900px]:grid-cols-1">
        <div
            v-for="issue in health.issues"
            :key="issue.key"
            class="grid grid-cols-[6px_minmax(0,1fr)] gap-3 overflow-hidden rounded-card border border-line bg-surface py-3.5 pr-4"
        >
            <div
                class="rounded-r"
                :class="STRIPE[issue.severity]"
                aria-hidden="true"
            ></div>
            <div>
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h3 class="m-0 text-sm font-semibold">{{ issue.title }}</h3>
                    <span class="font-display text-[22px] font-semibold num"
                        >{{ format(issue.count) }}
                        <span
                            v-if="issue.key !== 'waiting_imports'"
                            class="text-[13px] font-normal text-subtle"
                            >{{ share(issue.count, health.total) }}</span
                        ></span
                    >
                </div>
                <p class="m-0 mt-1 max-w-[70ch] text-[12.5px] text-subtle">
                    {{ issue.description }}
                </p>
                <div class="mt-2 text-[12.5px]">
                    <b class="text-ink">Rule:</b> {{ issue.rule }}
                </div>
                <Link
                    v-if="issue.audience && issue.count > 0"
                    :href="audienceIndex({ query: { filter: issue.audience } })"
                    class="mt-2 inline-block text-[12.5px] font-medium text-ink"
                    >See them in the Audience</Link
                >
            </div>
        </div>
    </div>
    <div
        v-else
        class="grid grid-cols-2 gap-3.5 max-[900px]:grid-cols-1"
        aria-hidden="true"
    >
        <div
            v-for="n in 6"
            :key="n"
            class="h-32 animate-pulse rounded-card bg-surface-2"
        ></div>
    </div>
</template>
