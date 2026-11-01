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
import { computed, ref, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import { http } from '@/lib/http';
import {
    show as showMerge,
    update as updateMerge,
} from '@/routes/imports/rows/merge';
import type {
    MergeComparison,
    RowUpdateResponse,
    SelectOption,
    StagedRow,
} from '@/types';

const props = defineProps<{ batchId: number; row: StagedRow | null }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ updated: [response: RowUpdateResponse] }>();

const comparison = ref<MergeComparison | null>(null);
const contactId = ref<number | null>(null);
const choices = ref<Record<string, 'incoming' | 'existing'>>({});
const loading = ref(false);
const saving = ref(false);

async function load(id: number | null = null): Promise<void> {
    if (!props.row) {
        return;
    }
    loading.value = true;
    try {
        const { data } = await http.get<MergeComparison>(
            showMerge({ batch: props.batchId, row: props.row.id }).url,
            { params: id ? { contact_id: id } : {} },
        );
        comparison.value = data;
        contactId.value = data.contact_id;
        choices.value = Object.fromEntries(
            data.fields.map((f) => [f.field, f.choice]),
        );
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => isOpen && void load());

const candidateOptions = computed<SelectOption<number>[]>(() =>
    (comparison.value?.candidates ?? []).map((c) => ({
        value: c.id,
        label: `${c.label} · matched by ${c.matched_by}`,
    })),
);

function pickContact(id: number | null): void {
    void load(id);
}

async function save(): Promise<void> {
    if (!props.row || contactId.value === null) {
        return;
    }
    saving.value = true;
    try {
        const { data } = await http.put<RowUpdateResponse>(
            updateMerge({ batch: props.batchId, row: props.row.id }).url,
            { contact_id: contactId.value, choices: choices.value },
        );
        emit('updated', data);
        open.value = false;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-20 bg-[rgba(10,20,17,.45)]" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-20 max-h-[calc(100%-32px)] w-[min(760px,calc(100%-32px))] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-[14px] border border-line bg-surface shadow-[0_20px_50px_rgba(0,0,0,.25)]"
            >
                <header
                    class="flex items-start justify-between gap-2.5 border-b border-line px-[18px] py-4"
                >
                    <div>
                        <DialogTitle
                            class="m-0 font-display text-lg font-semibold"
                        >
                            Merge row {{ row?.row_number }}
                        </DialogTitle>
                        <DialogDescription
                            class="m-0 text-[12.5px] text-subtle"
                        >
                            Choose which value to keep for each field. New
                            phones and emails are always added.
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                        >Close</DialogClose
                    >
                </header>
                <div class="flex flex-col gap-3.5 px-[18px] py-4">
                    <div
                        v-if="comparison && comparison.candidates.length > 1"
                        class="rounded-[9px] bg-sand-soft px-3 py-2.5 text-[12.5px]"
                    >
                        <b class="text-sand">Conflict:</b> this row matches
                        {{ comparison.candidates.length }} different contacts.
                        Pick the one it belongs to.
                        <div class="mt-2 max-w-sm">
                            <AppSelect
                                :model-value="contactId"
                                :options="candidateOptions"
                                :searchable="false"
                                size="sm"
                                aria-label="Contact to merge into"
                                @update:model-value="
                                    pickContact($event as number | null)
                                "
                            />
                        </div>
                    </div>

                    <table
                        v-if="comparison"
                        class="w-full border-collapse text-[12.5px]"
                        :class="{ 'opacity-60': loading }"
                    >
                        <thead>
                            <tr>
                                <th
                                    v-for="heading in [
                                        'Field',
                                        'Incoming (this file)',
                                        'Existing contact',
                                    ]"
                                    :key="heading"
                                    class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] text-subtle uppercase"
                                >
                                    {{ heading }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="field in comparison.fields"
                                :key="field.field"
                            >
                                <td
                                    class="border-t border-line px-3 py-2 font-medium"
                                >
                                    {{ field.label }}
                                </td>
                                <td
                                    v-for="side in [
                                        'incoming',
                                        'existing',
                                    ] as const"
                                    :key="side"
                                    class="border-t border-line px-3 py-2"
                                >
                                    <label
                                        class="flex cursor-pointer items-start gap-2"
                                    >
                                        <input
                                            v-model="choices[field.field]"
                                            type="radio"
                                            class="mt-[3px] accent-ink"
                                            :name="`choice-${field.field}`"
                                            :value="side"
                                            :disabled="
                                                field[side] === null &&
                                                field[
                                                    side === 'incoming'
                                                        ? 'existing'
                                                        : 'incoming'
                                                ] !== null
                                            "
                                        />
                                        <span
                                            dir="auto"
                                            :class="{
                                                'text-faint':
                                                    field[side] === null,
                                            }"
                                            >{{ field[side] ?? '—' }}</span
                                        >
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="h-40 animate-pulse rounded bg-surface-2"
                    ></div>

                    <p
                        v-if="comparison"
                        class="m-0 rounded-[9px] bg-surface-2 px-3 py-2.5 text-[12.5px] text-subtle"
                    >
                        <b class="text-fg">Names are never lost:</b> the name
                        you pick becomes the main name and the other one is
                        saved as another name of this contact.
                        <template v-if="comparison.other_names.length">
                            Already known as:
                            <template
                                v-for="(name, index) in comparison.other_names"
                                :key="name"
                                ><template v-if="index > 0">, </template
                                ><bdi dir="auto">{{ name }}</bdi></template
                            >.
                        </template>
                    </p>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                            :disabled="saving || contactId === null"
                            @click="save"
                        >
                            Merge into this contact
                        </button>
                        <span class="text-xs text-subtle"
                            >Applied when the import is committed.</span
                        >
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
