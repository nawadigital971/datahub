<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { current, dismissToast } = useToast();
</script>

<template>
    <div aria-live="polite" role="status">
        <Transition
            enter-from-class="opacity-0 translate-y-1"
            leave-to-class="opacity-0"
            enter-active-class="transition duration-150"
            leave-active-class="transition duration-150"
        >
            <button
                v-if="current"
                :key="current.id"
                type="button"
                class="fixed bottom-[calc(20px+env(safe-area-inset-bottom,0px))] left-1/2 z-30 -translate-x-1/2 rounded-lg px-3.5 py-2 text-[13px] text-bg"
                :class="current.type === 'error' ? 'bg-bad' : 'bg-fg'"
                @click="dismissToast"
            >
                {{ current.message }}
            </button>
        </Transition>
    </div>
</template>
