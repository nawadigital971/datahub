<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/branding/datacenter-icon.svg"
        alt=""
        :class="['rounded-[22%]', className]"
        v-bind="$attrs"
    />
</template>
