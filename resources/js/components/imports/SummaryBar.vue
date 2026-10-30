<script setup lang="ts">
import type { ImportBatch } from '@/types';

defineProps<{
    counts: ImportBatch['counts'];
    active: string | null;
}>();

defineEmits<{
    pick: [filter: { key: string; value: string } | null];
}>();

const TILES: {
    id: string;
    label: string;
    count: keyof ImportBatch['counts'];
    filter: { key: string; value: string } | null;
    tone?: string;
}[] = [
    { id: 'total', label: 'Total', count: 'total', filter: null },
    {
        id: 'clean',
        label: 'Clean',
        count: 'clean',
        filter: { key: 'severity', value: 'clean' },
    },
    {
        id: 'warnings',
        label: 'Warnings',
        count: 'warnings',
        filter: { key: 'severity', value: 'warning' },
        tone: 'text-warn',
    },
    {
        id: 'errors',
        label: 'Errors',
        count: 'errors',
        filter: { key: 'severity', value: 'error' },
        tone: 'text-bad',
    },
    {
        id: 'new',
        label: 'New',
        count: 'new',
        filter: { key: 'match', value: 'new' },
    },
    {
        id: 'merge',
        label: 'Merge',
        count: 'merge',
        filter: { key: 'match', value: 'matches_contact' },
    },
    {
        id: 'dup',
        label: 'Dup in batch',
        count: 'dup_in_batch',
        filter: { key: 'match', value: 'dup_in_batch' },
    },
    {
        id: 'conflict',
        label: 'Conflict',
        count: 'conflict',
        filter: { key: 'match', value: 'conflict' },
        tone: 'text-bad',
    },
    {
        id: 'skipped',
        label: 'Will skip',
        count: 'skipped',
        filter: { key: 'action', value: 'skip' },
    },
];

const format = (value: number): string => value.toLocaleString('en-US');
</script>

<template>
    <div
        class="grid grid-cols-9 overflow-hidden rounded-card border border-line bg-surface max-[1200px]:grid-cols-5 max-[560px]:grid-cols-3"
    >
        <button
            v-for="tile in TILES"
            :key="tile.id"
            type="button"
            class="border-0 border-r border-line bg-transparent px-3 py-2.5 text-left last:border-r-0 hover:bg-surface-2"
            :class="{ 'bg-ink-soft': active === tile.id }"
            :aria-pressed="active === tile.id"
            @click="$emit('pick', tile.filter)"
        >
            <div class="text-xs text-subtle">{{ tile.label }}</div>
            <div
                class="font-display text-[22px] leading-tight font-semibold num"
                :class="counts[tile.count] > 0 ? tile.tone : ''"
            >
                {{ format(counts[tile.count]) }}
            </div>
        </button>
    </div>
</template>
