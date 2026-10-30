<script setup lang="ts">
import AppToast from '@/components/AppToast.vue';
import AppTopBar from '@/components/AppTopBar.vue';
import type { BreadcrumbItem } from '@/types';

/** Accepted for older pages that still pass breadcrumbs; the top-bar shell does not show them. */
defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <div class="min-h-dvh">
        <AppTopBar />
        <main
            class="mx-auto max-w-shell pt-[18px] pr-[max(16px,env(safe-area-inset-right,0px))] pb-10 pl-[max(16px,env(safe-area-inset-left,0px))]"
        >
            <slot />
        </main>
        <AppToast />
    </div>
</template>
