<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
}>();

defineEmits<{
    remove: [];
}>();
</script>

<template>
    <button
        type="button"
        class="group inline-flex items-center gap-1.5 rounded-full border-0 bg-ink-soft py-[3px] pr-1.5 pl-2.5 text-xs text-fg"
        :aria-label="`Remove filter ${label} ${value}`"
        @click="$emit('remove')"
    >
        <span
            >{{ label }}:
            <b dir="auto" class="font-semibold text-ink">{{ value }}</b></span
        >
        <span
            class="grid size-[18px] place-items-center rounded-full text-[13px] leading-none text-subtle group-hover:bg-surface"
            aria-hidden="true"
            >×</span
        >
    </button>
</template>
