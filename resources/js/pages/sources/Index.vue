<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppDialog from '@/components/AppDialog.vue';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import { usePermissions } from '@/composables/usePermissions';
import { fieldClasses } from '@/lib/fieldClasses';
import { store as storeSource, update as updateSource } from '@/routes/sources';
import type { SelectOption } from '@/types';

type SourceRow = {
    id: number;
    slug: string;
    name: Record<string, string>;
    label: string;
    group_id: number;
    group: string | null;
    collected_year: number | null;
    lawful_basis: number;
    lawful_basis_label: string;
    notes: string | null;
    contacts: number;
    last_import: string | null;
};

defineProps<{
    sources: SourceRow[];
    groups: SelectOption<number>[];
    lawfulBases: SelectOption<number>[];
}>();

const { can } = usePermissions();
const UNKNOWN_BASIS = 4;

const editing = ref<SourceRow | null>(null);
const dialogOpen = ref(false);
const form = useForm({
    name: { en: '', ar: '' } as Record<string, string>,
    source_group_id: null as number | null,
    collected_year: null as number | null,
    lawful_basis: UNKNOWN_BASIS as number | null,
    notes: '',
});

function open(source: SourceRow | null): void {
    editing.value = source;
    form.clearErrors();
    form.name = { en: source?.name.en ?? '', ar: source?.name.ar ?? '' };
    form.source_group_id = source?.group_id ?? null;
    form.collected_year = source?.collected_year ?? null;
    form.lawful_basis = source?.lawful_basis ?? UNKNOWN_BASIS;
    form.notes = source?.notes ?? '';
    dialogOpen.value = true;
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };
    if (editing.value) {
        form.put(updateSource(String(editing.value.id)).url, options);
    } else {
        form.post(storeSource().url, options);
    }
}

const format = (value: number): string => value.toLocaleString('en-US');
const dateFormat = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });
const formatDate = (value: string | null): string =>
    value ? dateFormat.format(new Date(value)) : '—';
</script>

<template>
    <Head title="Sources" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Sources</h2>
        <button
            v-if="can('settings.update')"
            type="button"
            class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
            @click="open(null)"
        >
            Add source
        </button>
    </div>
    <p class="m-0 mb-4 max-w-[80ch] text-subtle">
        Every contact keeps the lists it came from. Record the lawful basis of
        each list: under UAE data protection law, marketing use needs one.
    </p>

    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'Source',
                                'Group',
                                'Collected',
                                'Lawful basis',
                                'Contacts',
                                'Last import',
                                'Notes',
                                '',
                            ]"
                            :key="heading"
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ heading }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="source in sources" :key="source.id">
                        <td class="border-t border-line px-3 py-2 align-top">
                            <strong dir="auto">{{ source.label }}</strong>
                            <div
                                v-if="source.name.ar"
                                class="text-xs text-subtle"
                                dir="rtl"
                                lang="ar"
                            >
                                {{ source.name.ar }}
                            </div>
                            <div class="font-mono text-[11px] text-faint">
                                {{ source.slug }}
                            </div>
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            {{ source.group ?? '—' }}
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top num"
                        >
                            {{ source.collected_year ?? '—' }}
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                :class="
                                    source.lawful_basis === UNKNOWN_BASIS
                                        ? 'bg-sand-soft text-sand'
                                        : 'bg-ink-soft text-ink'
                                "
                                >{{ source.lawful_basis_label }}</span
                            >
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 text-right align-top font-mono num"
                        >
                            {{ format(source.contacts) }}
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top whitespace-nowrap"
                        >
                            {{ formatDate(source.last_import) }}
                        </td>
                        <td
                            class="max-w-[260px] border-t border-line px-3 py-2 align-top text-subtle"
                            dir="auto"
                        >
                            {{ source.notes ?? '' }}
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 text-right align-top"
                        >
                            <button
                                v-if="can('settings.update')"
                                type="button"
                                class="border-0 bg-transparent p-0 font-medium text-ink"
                                @click="open(source)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <AppDialog
        v-model:open="dialogOpen"
        :title="editing ? `Edit ${editing.label}` : 'Add source'"
        description="A list contacts come from, such as a subscriber file or a directory."
    >
        <form class="flex flex-col gap-3.5" @submit.prevent="save">
            <div class="grid grid-cols-2 gap-3.5 max-[560px]:grid-cols-1">
                <FormField
                    v-slot="field"
                    label="Name in English"
                    required
                    :error="form.errors['name.en']"
                >
                    <input
                        :id="field.id"
                        v-model="form.name.en"
                        :class="fieldClasses"
                        required
                        aria-required="true"
                        maxlength="120"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Name in Arabic"
                    :error="form.errors['name.ar']"
                >
                    <input
                        :id="field.id"
                        v-model="form.name.ar"
                        :class="fieldClasses"
                        dir="rtl"
                        lang="ar"
                        maxlength="120"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Group"
                    :error="form.errors.source_group_id"
                >
                    <AppSelect
                        :id="field.id"
                        v-model="form.source_group_id"
                        :options="groups"
                        :searchable="false"
                        placeholder="No group"
                        :invalid="field.invalid"
                        :described-by="field.describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Year collected"
                    :error="form.errors.collected_year"
                >
                    <input
                        :id="field.id"
                        v-model.number="form.collected_year"
                        type="number"
                        min="1990"
                        :max="new Date().getFullYear() + 1"
                        :class="fieldClasses"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.describedBy"
                    />
                </FormField>
            </div>
            <FormField
                v-slot="field"
                label="Lawful basis"
                required
                :error="form.errors.lawful_basis"
                hint="Consent (e.g. subscribed), contract (customers), legitimate interest, or unknown until checked."
            >
                <AppSelect
                    :id="field.id"
                    v-model="form.lawful_basis"
                    :options="lawfulBases"
                    :searchable="false"
                    :required="true"
                    :invalid="field.invalid"
                    :described-by="field.describedBy"
                />
            </FormField>
            <FormField v-slot="field" label="Notes" :error="form.errors.notes">
                <textarea
                    :id="field.id"
                    v-model="form.notes"
                    rows="3"
                    maxlength="2000"
                    dir="auto"
                    :class="fieldClasses"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                ></textarea>
            </FormField>
            <div>
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save' : 'Add source' }}
                </button>
            </div>
        </form>
    </AppDialog>
</template>
