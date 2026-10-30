<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import FormField from '@/components/FormField.vue';
import Toggle from '@/components/Toggle.vue';
import { fieldClasses } from '@/lib/fieldClasses';
import { store as storeSegment } from '@/routes/segments';

const props = defineProps<{
    filters: Record<string, string>;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    is_shared: false,
    filters: {} as Record<string, string>,
});

function submit(): void {
    form.filters = props.filters;
    form.post(storeSegment().url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-20 bg-[rgba(10,20,17,.45)]" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-20 w-[min(460px,calc(100%-32px))] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-[14px] border border-line bg-surface shadow-[0_20px_50px_rgba(0,0,0,.25)]"
            >
                <form @submit.prevent="submit">
                    <header
                        class="flex items-start justify-between gap-2.5 border-b border-line px-[18px] py-4"
                    >
                        <div>
                            <DialogTitle
                                class="m-0 font-display text-lg font-semibold"
                            >
                                Save segment
                            </DialogTitle>
                            <DialogDescription
                                class="m-0 text-[12.5px] text-subtle"
                            >
                                Saves the current filters so you can apply them
                                in one click.
                            </DialogDescription>
                        </div>
                    </header>
                    <div class="flex flex-col gap-3.5 px-[18px] py-4">
                        <FormField
                            v-slot="field"
                            label="Name"
                            required
                            :error="form.errors.name"
                        >
                            <input
                                :id="field.id"
                                v-model="form.name"
                                :class="fieldClasses"
                                required
                                aria-required="true"
                                maxlength="120"
                                dir="auto"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.describedBy"
                            />
                        </FormField>
                        <Toggle
                            v-model="form.is_shared"
                            label="Share with the team"
                        />
                        <p
                            v-if="form.errors.filters"
                            class="m-0 text-xs text-bad"
                        >
                            {{ form.errors.filters }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <button
                                type="submit"
                                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                Save segment
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                                @click="open = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
