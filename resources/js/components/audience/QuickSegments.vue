<script setup lang="ts">
import type { AudienceSegment } from '@/types';

defineProps<{
    segments: AudienceSegment[];
    canManage: boolean;
}>();

defineEmits<{
    apply: [segment: AudienceSegment];
    remove: [segment: AudienceSegment];
    save: [];
}>();
</script>

<template>
    <div>
        <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="m-0 font-display text-base font-semibold">
                Quick segments
            </h2>
            <p class="m-0 text-[12.5px] text-subtle">
                Saved filter sets. Click to apply.
                <button
                    v-if="canManage"
                    type="button"
                    class="ml-1 border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-ink"
                    @click="$emit('save')"
                >
                    Save current filters
                </button>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <div
                v-for="segment in segments"
                :key="`saved-${segment.id}`"
                class="group relative"
            >
                <button
                    type="button"
                    class="rounded-lg border border-dashed border-line bg-surface px-[11px] py-[7px] text-left text-[12.5px] hover:border-solid hover:border-ink"
                    :class="{ 'pr-7': segment.can_delete && canManage }"
                    @click="$emit('apply', segment)"
                >
                    {{ segment.name }}
                    <small class="block text-[11px] text-subtle">{{
                        segment.subtitle
                    }}</small>
                </button>
                <button
                    v-if="segment.can_delete && canManage"
                    type="button"
                    class="absolute top-1 right-1 grid size-[18px] place-items-center rounded-full text-[13px] leading-none text-subtle opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-surface-2"
                    :aria-label="`Delete segment ${segment.name}`"
                    @click="$emit('remove', segment)"
                >
                    ×
                </button>
            </div>
            <p
                v-if="segments.length === 0"
                class="m-0 text-[12.5px] text-subtle"
            >
                No saved segments yet.
                <template v-if="canManage"
                    >Set the filters you want, then use “Save current
                    filters”.</template
                >
            </p>
        </div>
    </div>
</template>
