<script setup lang="ts">
import type { ChartBar as Bar } from '@/types';

defineProps<{
    title: string;
    subtitle: string;
    bars: Bar[] | null;
    selected: number[];
}>();

defineEmits<{
    toggle: [id: number];
}>();

const format = (value: number): string => value.toLocaleString('en-US');
const maxOf = (bars: Bar[]): number => Math.max(1, ...bars.map((b) => b.value));
</script>

<template>
    <div class="min-w-0 rounded-card border border-line bg-surface px-4 py-3.5">
        <h3 class="m-0 mb-0.5 text-[13.5px] font-semibold">{{ title }}</h3>
        <p class="m-0 mb-2.5 text-xs text-subtle">{{ subtitle }}</p>
        <div v-if="bars" class="flex flex-col gap-[5px]">
            <div
                v-for="bar in bars"
                :key="bar.id"
                class="grid cursor-pointer grid-cols-[minmax(0,150px)_minmax(0,1fr)_54px] items-center gap-2.5 rounded-[5px] px-[3px] py-px text-[12.5px] hover:bg-surface-2 max-[560px]:grid-cols-[minmax(0,100px)_minmax(0,1fr)_46px]"
                role="button"
                tabindex="0"
                :aria-pressed="selected.includes(bar.id)"
                @click="$emit('toggle', bar.id)"
                @keydown.enter.prevent="$emit('toggle', bar.id)"
                @keydown.space.prevent="$emit('toggle', bar.id)"
            >
                <span
                    class="truncate"
                    :class="{
                        'font-semibold text-ink': selected.includes(bar.id),
                    }"
                    dir="auto"
                    :title="bar.label"
                    >{{ bar.label }}</span
                >
                <span
                    class="relative h-3.5 overflow-hidden rounded-[3px] bg-surface-2"
                >
                    <span
                        class="absolute inset-y-0 left-0 rounded-[3px] bg-bar"
                        :style="{
                            width: `${((bar.value / maxOf(bars)) * 100).toFixed(1)}%`,
                        }"
                    ></span>
                </span>
                <span class="text-right font-mono text-xs text-subtle num">{{
                    format(bar.value)
                }}</span>
            </div>
            <p v-if="bars.length === 0" class="m-0 text-xs text-subtle">
                No contacts match.
            </p>
        </div>
        <div v-else class="flex flex-col gap-[5px]" aria-hidden="true">
            <div
                v-for="n in 6"
                :key="n"
                class="h-4 animate-pulse rounded bg-surface-2"
            ></div>
        </div>
    </div>
</template>
