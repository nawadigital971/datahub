<script setup lang="ts">
import axios from 'axios';
import { nextTick, ref } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import { toast } from '@/composables/useToast';
import { errorMessage, http } from '@/lib/http';
import { update as updateRow } from '@/routes/imports/rows';
import type { RowUpdateResponse, SelectOption } from '@/types';

/**
 * A preview cell that saves on Enter/blur (text) or on change (select). The server re-normalizes and
 * re-checks the whole row and returns it with fresh batch counts.
 */
const props = withDefaults(
    defineProps<{
        batchId: number;
        rowId: number;
        field: string;
        value: string | number | null;
        display?: string | null;
        options?: SelectOption<string | number>[];
        disabled?: boolean;
        dir?: 'auto' | 'ltr' | 'rtl';
        mono?: boolean;
        label: string;
    }>(),
    {
        display: undefined,
        options: undefined,
        disabled: false,
        dir: 'auto',
        mono: false,
    },
);

const emit = defineEmits<{ updated: [response: RowUpdateResponse] }>();

const editing = ref(false);
const draft = ref('');
const saving = ref(false);
const error = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);

async function startEditing(): Promise<void> {
    if (props.disabled || props.options) {
        return;
    }
    draft.value = props.value === null ? '' : String(props.value);
    error.value = null;
    editing.value = true;
    await nextTick();
    input.value?.focus();
    input.value?.select();
}

async function save(value: string | number | null): Promise<void> {
    if (String(value ?? '') === String(props.value ?? '')) {
        editing.value = false;

        return;
    }

    saving.value = true;
    try {
        const { data } = await http.patch<RowUpdateResponse>(
            updateRow({ batch: props.batchId, row: props.rowId }).url,
            { field: props.field, value: value === '' ? null : value },
            { silent: true },
        );
        editing.value = false;
        error.value = null;
        emit('updated', data);
    } catch (exception) {
        const message = errorMessage(exception);
        if (
            axios.isAxiosError(exception) &&
            exception.response?.status === 422
        ) {
            const errors = (
                exception.response.data as { errors?: Record<string, string[]> }
            ).errors;
            error.value = errors?.value?.[0] ?? message;
        } else {
            toast(message, 'error');
        }
    } finally {
        saving.value = false;
    }
}

function cancel(): void {
    editing.value = false;
    error.value = null;
}
</script>

<template>
    <div class="min-w-0">
        <AppSelect
            v-if="options"
            :model-value="value"
            :options="options"
            size="sm"
            :disabled="disabled || saving"
            :aria-label="label"
            @update:model-value="save($event as string | number | null)"
        />
        <input
            v-else-if="editing"
            ref="input"
            v-model="draft"
            class="w-full min-w-[120px] rounded-md border border-ink bg-surface px-1.5 py-1 text-base sm:text-[12.5px]"
            :class="{ 'font-mono': mono, 'border-bad': error }"
            :dir="dir"
            :aria-label="label"
            :aria-invalid="error ? true : undefined"
            :disabled="saving"
            @keydown.enter.prevent="save(draft)"
            @keydown.escape.prevent="cancel"
            @blur="save(draft)"
        />
        <button
            v-else
            type="button"
            class="block w-full min-w-[60px] cursor-text rounded-md border border-transparent bg-transparent px-1.5 py-1 text-left hover:border-line hover:bg-surface-2 disabled:cursor-default disabled:hover:border-transparent disabled:hover:bg-transparent"
            :class="{ 'font-mono': mono }"
            :dir="dir"
            :disabled="disabled"
            :aria-label="`Edit ${label}`"
            @click="startEditing"
        >
            <template
                v-if="(display ?? value) !== null && (display ?? value) !== ''"
                >{{ display ?? value }}</template
            >
            <span v-else class="text-faint">—</span>
        </button>
        <p v-if="error" class="m-0 mt-0.5 text-[11px] text-bad">{{ error }}</p>
    </div>
</template>
