<script setup lang="ts">
import { computed } from 'vue';
import type { Counts, FacetOption } from '@/types';

const props = defineProps<{
    label: string;
    options: FacetOption[];
    selected: number[];
    counts: Counts | null;
}>();

defineEmits<{
    toggle: [id: number];
    clear: [];
}>();

const countFor = (id: number): number | null =>
    props.counts === null
        ? null
        : ((props.counts as Record<string, number>)[id] ?? 0);

/** Most common first once counts are known (as in the prototype). */
const sortedOptions = computed(() =>
    props.counts === null
        ? props.options
        : [...props.options].sort(
              (a, b) => (countFor(b.id) ?? 0) - (countFor(a.id) ?? 0),
          ),
);

const format = (value: number): string => value.toLocaleString('en-US');
</script>

<template>
    <div class="border-b border-line px-3.5 py-3 last:border-b-0">
        <h3
            class="m-0 mb-2 flex justify-between text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
        >
            <span>{{ label }}</span>
            <button
                v-if="selected.length"
                type="button"
                class="border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium tracking-normal text-ink normal-case"
                @click="$emit('clear')"
            >
                Clear {{ selected.length }}
            </button>
        </h3>
        <div class="flex max-h-[190px] flex-col gap-0.5 overflow-auto">
            <label
                v-for="option in sortedOptions"
                :key="option.id"
                class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-[13px] hover:bg-surface-2"
                :class="{ 'opacity-45': countFor(option.id) === 0 }"
            >
                <input
                    type="checkbox"
                    class="m-0 accent-ink"
                    :checked="selected.includes(option.id)"
                    @change="$emit('toggle', option.id)"
                />
                <span class="min-w-0 flex-1 truncate" dir="auto">{{
                    option.label
                }}</span>
                <span
                    v-if="countFor(option.id) !== null"
                    class="font-mono text-xs text-faint num"
                    >{{ format(countFor(option.id) ?? 0) }}</span
                >
                <span
                    v-else
                    class="h-3 w-8 animate-pulse rounded bg-surface-2"
                    aria-hidden="true"
                ></span>
            </label>
        </div>
    </div>
</template>
