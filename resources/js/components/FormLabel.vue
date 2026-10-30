<script setup lang="ts">
defineProps<{
    for?: string;
    /** Must match the backend Form Request rules: required shows `*`, otherwise `(optional)`. */
    required?: boolean;
}>();
</script>

<template>
    <label :for="$props.for" class="text-[13px] font-medium text-fg">
        <slot />
        <span v-if="required" class="ml-0.5 text-bad" aria-hidden="true"
            >*</span
        >
        <span v-else class="ml-1 font-normal text-faint">(optional)</span>
    </label>
</template>
