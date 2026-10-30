<script setup lang="ts">
const model = defineModel<boolean>({ default: false });

defineProps<{
    label?: string;
    disabled?: boolean;
    name?: string;
}>();
</script>

<template>
    <label
        class="flex items-center justify-between gap-2.5 py-1 text-[13px]"
        :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
    >
        <span
            ><slot>{{ label }}</slot></span
        >
        <input
            v-model="model"
            type="checkbox"
            role="switch"
            class="nw-switch"
            :name="name"
            :disabled="disabled"
            :aria-checked="model"
        />
    </label>
</template>

<style scoped>
/* Exact copy of the prototype's .switch */
.nw-switch {
    appearance: none;
    width: 34px;
    height: 20px;
    border-radius: 99px;
    background: var(--color-line);
    position: relative;
    flex: none;
    cursor: inherit;
    transition: background 0.15s;
    margin: 3px 3px 3px 4px;
}

.nw-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--color-surface);
    transition: transform 0.15s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.nw-switch:checked {
    background: var(--color-ink);
}

.nw-switch:checked::after {
    transform: translateX(14px);
}
</style>
