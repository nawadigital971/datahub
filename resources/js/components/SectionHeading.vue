<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">{{ title }}</h2>
        <p v-if="description" class="m-0 text-[12.5px] text-subtle">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
