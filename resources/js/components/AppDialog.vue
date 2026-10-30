<script setup lang="ts">
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';

/** The prototype's dialog: title, optional description, Close button, scrollable body. */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        width?: string;
    }>(),
    { description: undefined, width: '520px' },
);

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-20 bg-[rgba(10,20,17,.45)]" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-20 max-h-[calc(100%-32px)] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-[14px] border border-line bg-surface shadow-[0_20px_50px_rgba(0,0,0,.25)]"
                :style="{ width: `min(${width}, calc(100% - 32px))` }"
            >
                <header
                    class="flex items-start justify-between gap-2.5 border-b border-line px-[18px] py-4"
                >
                    <div>
                        <DialogTitle
                            class="m-0 font-display text-lg font-semibold"
                            >{{ title }}</DialogTitle
                        >
                        <DialogDescription
                            v-if="description"
                            class="m-0 text-[12.5px] text-subtle"
                            >{{ description }}</DialogDescription
                        >
                    </div>
                    <DialogClose
                        class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                        >Close</DialogClose
                    >
                </header>
                <div class="flex flex-col gap-3.5 px-[18px] py-4">
                    <slot />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
