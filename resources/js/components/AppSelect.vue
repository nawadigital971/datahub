<script setup lang="ts" generic="T extends string | number">
import { useDebounceFn } from '@vueuse/core';
import { Check, ChevronDown } from '@lucide/vue';
import {
    ListboxContent,
    ListboxFilter,
    ListboxItem,
    ListboxRoot,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, shallowReactive, shallowRef, watch } from 'vue';
import { compactFieldClasses, fieldClasses } from '@/lib/fieldClasses';
import { http } from '@/lib/http';
import type { SelectOption } from '@/types';

/**
 * The app's only select input (native <select> is not used). Searchable, single or multiple,
 * with static `options` or async options loaded from `endpoint` (expects `{ data: SelectOption[] }`,
 * called with `?search=…` plus `params`).
 */
const props = withDefaults(
    defineProps<{
        options?: SelectOption<T>[];
        multiple?: boolean;
        searchable?: boolean;
        placeholder?: string;
        searchPlaceholder?: string;
        endpoint?: string;
        params?: Record<string, string | number | boolean | null>;
        size?: 'md' | 'sm';
        id?: string;
        name?: string;
        disabled?: boolean;
        required?: boolean;
        invalid?: boolean;
        describedBy?: string;
        ariaLabel?: string;
    }>(),
    {
        options: () => [],
        searchable: true,
        placeholder: 'Select…',
        searchPlaceholder: 'Search…',
        size: 'md',
    },
);

const model = defineModel<T | T[] | null>({ default: null });

const open = ref(false);
const search = ref('');
const loading = ref(false);
const remoteOptions = shallowRef<SelectOption<T>[]>([]);

/** Labels of every option seen so far, so selected async values keep their label. */
const knownLabels = shallowReactive(new Map<T, string>());

const remember = (options: SelectOption<T>[]): void => {
    options.forEach((option) => knownLabels.set(option.value, option.label));
};

watch(() => props.options, remember, { immediate: true });

const normalize = (text: string): string =>
    text
        .toLocaleLowerCase()
        .normalize('NFKD')
        .replace(/[ً-ٟـ]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي');

const visibleOptions = computed<SelectOption<T>[]>(() => {
    if (props.endpoint) {
        return remoteOptions.value;
    }

    const query = normalize(search.value.trim());

    return query === ''
        ? props.options
        : props.options.filter((option) =>
              normalize(option.label).includes(query),
          );
});

let requestController: AbortController | undefined;

const fetchOptions = async (): Promise<void> => {
    if (!props.endpoint) {
        return;
    }

    requestController?.abort();
    requestController = new AbortController();
    loading.value = true;

    try {
        const response = await http.get<{ data: SelectOption<T>[] }>(
            props.endpoint,
            {
                params: { ...props.params, search: search.value || null },
                signal: requestController.signal,
            },
        );
        remoteOptions.value = response.data.data;
        remember(remoteOptions.value);
    } catch {
        // The shared http client already shows the error toast.
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = useDebounceFn(fetchOptions, 250);

watch(search, () => {
    if (props.endpoint) {
        void debouncedFetch();
    }
});

watch(open, (isOpen) => {
    if (!isOpen) {
        search.value = '';

        return;
    }

    if (props.endpoint) {
        void fetchOptions();
    }
});

const selectedValues = computed<T[]>(() => {
    if (model.value === null || model.value === undefined) {
        return [];
    }

    return Array.isArray(model.value) ? model.value : [model.value];
});

const labelFor = (value: T): string => knownLabels.get(value) ?? String(value);

const summary = computed<string>(() => {
    const [first, ...rest] = selectedValues.value;

    if (first === undefined) {
        return '';
    }

    return rest.length === 0
        ? labelFor(first)
        : `${labelFor(first)} +${rest.length}`;
});

const listboxValue = computed<AcceptableValue | AcceptableValue[] | undefined>(
    () => (props.multiple ? selectedValues.value : (model.value ?? undefined)),
);

const onSelect = (
    value: AcceptableValue | AcceptableValue[] | undefined,
): void => {
    if (props.multiple) {
        model.value = (Array.isArray(value) ? value : []) as T[];

        return;
    }

    model.value = (value ?? null) as T | null;
    open.value = false;
};

const triggerClasses = computed(() =>
    props.size === 'sm' ? compactFieldClasses : fieldClasses,
);
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            :id="id"
            type="button"
            :disabled="disabled"
            :aria-label="ariaLabel"
            :aria-invalid="invalid || undefined"
            :aria-required="required || undefined"
            :aria-describedby="describedBy"
            :class="[
                triggerClasses,
                'flex items-center justify-between gap-2 text-left',
            ]"
        >
            <span v-if="summary" class="min-w-0 truncate" dir="auto">{{
                summary
            }}</span>
            <span v-else class="min-w-0 truncate text-faint">{{
                placeholder
            }}</span>
            <ChevronDown class="size-3.5 flex-none text-subtle" />
        </PopoverTrigger>

        <template v-if="name">
            <input
                v-for="value in selectedValues"
                :key="String(value)"
                type="hidden"
                :name="multiple ? `${name}[]` : name"
                :value="value"
            />
        </template>

        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="4"
                class="z-50 w-(--reka-popover-trigger-width) min-w-48 rounded-lg border border-line bg-surface p-1 shadow-[0_8px_24px_rgba(0,0,0,.12)]"
            >
                <ListboxRoot
                    :model-value="listboxValue"
                    :multiple="multiple"
                    selection-behavior="toggle"
                    highlight-on-hover
                    class="flex flex-col gap-1"
                    @update:model-value="onSelect"
                >
                    <ListboxFilter
                        v-if="searchable"
                        v-model="search"
                        auto-focus
                        dir="auto"
                        :placeholder="searchPlaceholder"
                        :class="fieldClasses"
                    />
                    <ListboxContent
                        class="flex max-h-[190px] flex-col gap-0.5 overflow-auto"
                    >
                        <ListboxItem
                            v-for="option in visibleOptions"
                            :key="String(option.value)"
                            :value="option.value"
                            :disabled="option.disabled"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-[13px] outline-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-45 data-[highlighted]:bg-surface-2"
                        >
                            <span
                                class="grid size-3.5 flex-none place-items-center border border-faint"
                                :class="[
                                    multiple ? 'rounded-[3px]' : 'rounded-full',
                                    selectedValues.includes(option.value)
                                        ? 'border-ink bg-ink text-ink-fg'
                                        : 'bg-surface',
                                ]"
                                aria-hidden="true"
                            >
                                <Check
                                    v-if="selectedValues.includes(option.value)"
                                    class="size-2.5"
                                    :stroke-width="3"
                                />
                            </span>
                            <span class="min-w-0 flex-1 truncate" dir="auto">{{
                                option.label
                            }}</span>
                            <span
                                v-if="option.hint !== undefined"
                                class="font-mono text-xs text-faint num"
                                >{{ option.hint }}</span
                            >
                        </ListboxItem>
                        <p
                            v-if="loading && visibleOptions.length === 0"
                            class="m-0 px-1.5 py-1 text-[13px] text-subtle"
                        >
                            Loading…
                        </p>
                        <p
                            v-else-if="visibleOptions.length === 0"
                            class="m-0 px-1.5 py-1 text-[13px] text-subtle"
                        >
                            No matches.
                        </p>
                    </ListboxContent>
                </ListboxRoot>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
