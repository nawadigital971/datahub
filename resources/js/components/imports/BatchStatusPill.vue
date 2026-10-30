<script setup lang="ts">
import type { BatchStatusKey } from '@/types';

defineProps<{
    status: { key: BatchStatusKey; label: string };
}>();

const CLASSES: Record<BatchStatusKey, string> = {
    mapping: 'bg-sand-soft text-sand',
    analyzing: 'bg-sand-soft text-sand',
    ready: 'bg-ink-soft text-ink',
    committing: 'bg-sand-soft text-sand',
    committed: 'bg-ink-soft text-ink',
    discarded: 'bg-surface-2 text-subtle',
    failed: 'bg-bad-soft text-bad',
};
</script>

<template>
    <span
        class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
        :class="CLASSES[status.key]"
        >{{ status.label }}</span
    >
</template>
