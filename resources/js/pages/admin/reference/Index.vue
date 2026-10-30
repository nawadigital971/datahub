<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import AppDialog from '@/components/AppDialog.vue';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import { toast } from '@/composables/useToast';
import { fieldClasses } from '@/lib/fieldClasses';
import {
    destroy as destroyItem,
    index as referenceIndex,
    store as storeItem,
    update as updateItem,
} from '@/routes/admin/reference';
import type { SelectOption } from '@/types';

type FieldKind =
    | 'text'
    | 'textarea'
    | 'number'
    | 'translated'
    | 'aliases'
    | 'select'
    | 'map';
type Field = {
    name: string;
    label: string;
    kind: FieldKind;
    required: boolean;
    hint?: string;
    options?: SelectOption<string | number>[];
};
type MapPair = { header: string; target: string | null };
type FormValue =
    | string
    | number
    | null
    | string[]
    | Record<string, string>
    | MapPair[];
type Item = { id: number; title: string; values: Record<string, FormValue> };

const props = defineProps<{
    types: { key: string; label: string }[];
    type: {
        key: string;
        label: string;
        description: string;
        permission: string;
        fields: Field[];
    };
    items: {
        data: Item[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string | null };
    locales: string[];
}>();

const LOCALE_NAMES: Record<string, string> = { en: 'English', ar: 'Arabic' };

/* ---------- search ---------- */
const search = ref(props.filters.search ?? '');
const runSearch = useDebounceFn(() => {
    router.get(
        referenceIndex(props.type.key).url,
        search.value ? { filter: { search: search.value } } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

/* ---------- table ---------- */
const columns = computed(() =>
    props.type.fields.filter((field) => field.kind !== 'textarea').slice(0, 5),
);

function optionLabel(field: Field, value: unknown): string {
    const option = field.options?.find((o) => o.value === value);

    return option ? option.label : value == null ? '—' : String(value);
}

function display(field: Field, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    switch (field.kind) {
        case 'translated':
            return (value as Record<string, string>).en ?? '—';
        case 'aliases': {
            const list = value as string[];
            return list.length === 0
                ? '—'
                : list.slice(0, 4).join(', ') +
                      (list.length > 4 ? ` +${list.length - 4}` : '');
        }
        case 'select':
            return optionLabel(field, value);
        case 'map': {
            const count = (value as MapPair[]).length;
            return `${count} ${count === 1 ? 'column' : 'columns'}`;
        }
        default:
            return String(value);
    }
}

const arabic = (field: Field, value: unknown): string | null =>
    field.kind === 'translated'
        ? ((value as Record<string, string> | null)?.ar ?? null)
        : null;

/* ---------- add / edit ---------- */
const dialogOpen = ref(false);
const editing = ref<Item | null>(null);
const confirmingDelete = ref(false);
const form = useForm<{ values: Record<string, FormValue> }>({ values: {} });
const aliasText = ref<Record<string, string>>({});

function emptyValue(field: Field): FormValue {
    switch (field.kind) {
        case 'translated':
            return Object.fromEntries(props.locales.map((l) => [l, '']));
        case 'aliases':
            return [];
        case 'map':
            return [{ header: '', target: null }];
        default:
            return null;
    }
}

function open(item: Item | null): void {
    editing.value = item;
    confirmingDelete.value = false;
    form.clearErrors();
    const values: Record<string, FormValue> = {};
    for (const field of props.type.fields) {
        const current = item?.values[field.name];
        if (field.kind === 'translated') {
            values[field.name] = {
                ...(emptyValue(field) as Record<string, string>),
                ...(current as Record<string, string> | undefined),
            };
        } else if (field.kind === 'map') {
            const pairs = (current as MapPair[] | undefined) ?? [];
            values[field.name] = pairs.length
                ? pairs.map((p) => ({ ...p }))
                : emptyValue(field);
        } else {
            values[field.name] =
                current === undefined ? emptyValue(field) : current;
        }
        if (field.kind === 'aliases') {
            aliasText.value[field.name] = (
                (values[field.name] as string[]) ?? []
            ).join('\n');
        }
    }
    form.values = values;
    dialogOpen.value = true;
}

const translated = (name: string): Record<string, string> =>
    form.values[name] as Record<string, string>;
const pairs = (name: string): MapPair[] => form.values[name] as MapPair[];
const errorAt = (key: string): string | undefined =>
    (form.errors as Record<string, string>)[key];
const error = (name: string): string | undefined => {
    const errors = form.errors as Record<string, string>;
    return (
        errors[`values.${name}`] ??
        Object.entries(errors).find(([key]) =>
            key.startsWith(`values.${name}.`),
        )?.[1]
    );
};

function save(): void {
    for (const field of props.type.fields) {
        if (field.kind === 'aliases') {
            form.values[field.name] = (aliasText.value[field.name] ?? '')
                .split(/\n|,/)
                .map((alias) => alias.trim())
                .filter(Boolean);
        }
        if (field.kind === 'map') {
            form.values[field.name] = pairs(field.name).filter(
                (pair) => pair.header.trim() !== '' || pair.target,
            );
        }
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };
    if (editing.value) {
        form.put(updateItem([props.type.key, editing.value.id]).url, options);
    } else {
        form.post(storeItem(props.type.key).url, options);
    }
}

function remove(): void {
    if (!editing.value) {
        return;
    }
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;
        return;
    }
    router.delete(destroyItem([props.type.key, editing.value.id]).url, {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
        onError: (errors) =>
            toast(Object.values(errors)[0] ?? 'Could not delete.', 'error'),
    });
}

const singular = computed(() =>
    props.type.key === 'do_not_contact'
        ? 'entry'
        : props.type.label.toLowerCase().replace(/s$/, ''),
);
</script>

<template>
    <Head title="Reference data" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Reference data</h2>
        <button
            type="button"
            class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
            @click="open(null)"
        >
            Add {{ singular }}
        </button>
    </div>
    <p class="m-0 mb-3 max-w-[80ch] text-subtle">
        Everything the import cleaning reads from. Changes apply to the next
        analysis; re-check a staged batch to apply them to its rows. Claude can
        read and edit these lists over MCP too.
    </p>

    <nav class="mb-3.5 flex flex-wrap gap-1.5" aria-label="Reference lists">
        <Link
            v-for="tab in types"
            :key="tab.key"
            :href="referenceIndex(tab.key)"
            class="rounded-full border px-3 py-1 text-[12.5px] font-medium no-underline"
            :class="
                tab.key === type.key
                    ? 'border-ink bg-ink-soft text-ink'
                    : 'border-line bg-surface text-fg'
            "
            :aria-current="tab.key === type.key ? 'page' : undefined"
            preserve-scroll
            >{{ tab.label }}</Link
        >
    </nav>

    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <div class="min-w-0">
                <strong class="num">{{
                    items.total.toLocaleString('en-US')
                }}</strong>
                <span class="text-subtle"> · {{ type.label }}</span>
                <p class="m-0 max-w-[90ch] text-xs text-subtle">
                    {{ type.description }}
                </p>
            </div>
            <input
                v-model="search"
                type="search"
                :placeholder="`Search ${type.label.toLowerCase()}…`"
                :aria-label="`Search ${type.label.toLowerCase()}`"
                class="w-64 rounded-lg border border-line bg-surface-2 px-2.5 py-1.5 text-base sm:text-[13px]"
                @input="runSearch"
            />
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="field in columns"
                            :key="field.name"
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ field.label }}
                        </th>
                        <th class="bg-surface-2 px-3 py-[9px]"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                        <td
                            v-for="field in columns"
                            :key="field.name"
                            class="max-w-[320px] border-t border-line px-3 py-2 align-top"
                        >
                            <span
                                :class="{
                                    'font-mono text-[11.5px]':
                                        field.name === 'slug' ||
                                        field.name === 'key',
                                }"
                                dir="auto"
                                >{{
                                    display(field, item.values[field.name])
                                }}</span
                            >
                            <div
                                v-if="arabic(field, item.values[field.name])"
                                class="text-xs text-subtle"
                                dir="rtl"
                                lang="ar"
                            >
                                {{ arabic(field, item.values[field.name]) }}
                            </div>
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 text-right align-top"
                        >
                            <button
                                type="button"
                                class="border-0 bg-transparent p-0 font-medium text-ink"
                                @click="open(item)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td
                            :colspan="columns.length + 1"
                            class="border-t border-line px-3 py-6 text-center text-subtle"
                        >
                            Nothing here yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="items.last_page > 1"
            class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
        >
            <span class="mr-auto num"
                >Page {{ items.current_page }} of {{ items.last_page }}</span
            >
            <Link
                v-if="items.prev_page_url"
                :href="items.prev_page_url"
                preserve-scroll
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Previous</Link
            >
            <Link
                v-if="items.next_page_url"
                :href="items.next_page_url"
                preserve-scroll
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Next</Link
            >
        </div>
    </div>

    <AppDialog
        v-model:open="dialogOpen"
        :title="editing ? `Edit ${editing.title}` : `Add ${singular}`"
        :description="type.label"
    >
        <form class="flex flex-col gap-3.5" @submit.prevent="save">
            <template v-for="field in type.fields" :key="field.name">
                <div
                    v-if="field.kind === 'translated'"
                    class="grid grid-cols-2 gap-3.5 max-[560px]:grid-cols-1"
                >
                    <FormField
                        v-for="locale in locales"
                        :key="locale"
                        v-slot="slot"
                        :label="`${field.label} in ${LOCALE_NAMES[locale] ?? locale}`"
                        :required="field.required && locale === locales[0]"
                        :error="
                            errorAt(`values.${field.name}.${locale}`) ??
                            (locale === locales[0]
                                ? error(field.name)
                                : undefined)
                        "
                    >
                        <input
                            :id="slot.id"
                            v-model="translated(field.name)[locale]"
                            :class="fieldClasses"
                            :dir="locale === 'ar' ? 'rtl' : 'auto'"
                            :lang="locale"
                            maxlength="120"
                            :required="field.required && locale === locales[0]"
                            :aria-required="
                                field.required && locale === locales[0]
                            "
                            :aria-invalid="slot.invalid"
                            :aria-describedby="slot.describedBy"
                        />
                    </FormField>
                </div>

                <FormField
                    v-else-if="field.kind === 'map'"
                    v-slot="slot"
                    :label="field.label"
                    :required="field.required"
                    :hint="field.hint"
                    :error="error(field.name)"
                >
                    <div :id="slot.id" class="flex flex-col gap-2">
                        <div
                            v-for="(pair, index) in pairs(field.name)"
                            :key="index"
                            class="grid grid-cols-[1fr_1fr_auto] items-center gap-2"
                        >
                            <input
                                v-model="pair.header"
                                :class="fieldClasses"
                                placeholder="Column header"
                                aria-label="Column header"
                                dir="auto"
                            />
                            <AppSelect
                                v-model="pair.target"
                                :options="field.options ?? []"
                                aria-label="Goes to"
                                placeholder="Goes to…"
                            />
                            <button
                                type="button"
                                class="border-0 bg-transparent p-0 text-[12.5px] font-medium text-bad"
                                :aria-label="`Remove ${pair.header || 'row'}`"
                                @click="pairs(field.name).splice(index, 1)"
                            >
                                Remove
                            </button>
                        </div>
                        <div>
                            <button
                                type="button"
                                class="rounded-lg border border-line bg-transparent px-3 py-1.5 text-[12.5px] font-medium text-fg"
                                @click="
                                    pairs(field.name).push({
                                        header: '',
                                        target: null,
                                    })
                                "
                            >
                                Add column
                            </button>
                        </div>
                    </div>
                </FormField>

                <FormField
                    v-else
                    v-slot="slot"
                    :label="field.label"
                    :required="field.required"
                    :hint="field.hint"
                    :error="error(field.name)"
                >
                    <AppSelect
                        v-if="field.kind === 'select'"
                        :id="slot.id"
                        v-model="
                            form.values[field.name] as string | number | null
                        "
                        :options="field.options ?? []"
                        :searchable="(field.options?.length ?? 0) > 8"
                        :required="field.required"
                        :invalid="slot.invalid"
                        :described-by="slot.describedBy"
                    />
                    <textarea
                        v-else-if="field.kind === 'aliases'"
                        :id="slot.id"
                        v-model="aliasText[field.name]"
                        rows="4"
                        dir="auto"
                        placeholder="One per line"
                        :class="fieldClasses"
                        :aria-invalid="slot.invalid"
                        :aria-describedby="slot.describedBy"
                    ></textarea>
                    <textarea
                        v-else-if="field.kind === 'textarea'"
                        :id="slot.id"
                        v-model="form.values[field.name] as string"
                        rows="3"
                        maxlength="2000"
                        dir="auto"
                        :class="fieldClasses"
                        :aria-invalid="slot.invalid"
                        :aria-describedby="slot.describedBy"
                    ></textarea>
                    <input
                        v-else
                        :id="slot.id"
                        v-model="form.values[field.name] as string"
                        :type="field.kind === 'number' ? 'number' : 'text'"
                        dir="auto"
                        :class="fieldClasses"
                        :required="field.required"
                        :aria-required="field.required"
                        :aria-invalid="slot.invalid"
                        :aria-describedby="slot.describedBy"
                    />
                </FormField>
            </template>

            <p v-if="errorAt('item')" class="m-0 text-xs text-bad">
                {{ errorAt('item') }}
            </p>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save' : `Add ${singular}` }}
                </button>
                <button
                    v-if="editing"
                    type="button"
                    class="ml-auto rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-bad"
                    @click="remove"
                >
                    {{ confirmingDelete ? 'Click again to delete' : 'Delete' }}
                </button>
            </div>
        </form>
    </AppDialog>
</template>
