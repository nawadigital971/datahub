<script setup lang="ts">
import { VueTelInput } from 'vue-tel-input';
import type { PhoneObject } from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';

/**
 * Phone field. The model is E.164 (e.g. `+971501234567`) once the number parses;
 * the server validates it again with libphonenumber.
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        placeholder?: string;
        defaultCountry?: string;
        preferredCountries?: string[];
        disabled?: boolean;
        required?: boolean;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        placeholder: '50 123 4567',
        defaultCountry: 'AE',
        preferredCountries: () => [
            'AE',
            'SA',
            'KW',
            'QA',
            'BH',
            'OM',
            'EG',
            'JO',
            'LB',
            'SY',
            'IN',
            'PK',
            'PH',
            'GB',
        ],
    },
);

const onInput = (raw: string, phone: PhoneObject): void => {
    if (raw.trim() === '') {
        model.value = null;

        return;
    }

    model.value = phone.number || raw;
};
</script>

<template>
    <div class="nw-phone" :aria-invalid="invalid || undefined">
        <VueTelInput
            :model-value="model ?? ''"
            mode="international"
            :default-country="defaultCountry"
            :preferred-countries="preferredCountries"
            :disabled="disabled"
            :auto-default-country="false"
            :valid-characters-only="true"
            :input-options="{
                id: props.id,
                name: props.name,
                placeholder: props.placeholder,
                required: props.required,
                autocomplete: 'tel',
                type: 'tel',
                showDialCode: false,
            }"
            :dropdown-options="{
                showSearchBox: true,
                showFlags: true,
                showDialCodeInList: true,
                searchBoxPlaceholder: 'Search country…',
            }"
            :aria-describedby="describedBy"
            @on-input="onInput"
        />
    </div>
</template>

<style scoped>
/* vue-tel-input restyled to match the shared field look (fieldClasses). */
.nw-phone :deep(.vue-tel-input) {
    border: 1px solid var(--color-line);
    border-radius: 8px;
    background: var(--color-surface-2);
    box-shadow: none;
    color: var(--color-fg);
}

.nw-phone[aria-invalid='true'] :deep(.vue-tel-input) {
    border-color: var(--color-bad);
}

.nw-phone :deep(.vue-tel-input:focus-within) {
    box-shadow: none;
    border-color: var(--color-line);
    outline: 2px solid var(--color-ink);
    outline-offset: 2px;
}

.nw-phone :deep(.vue-tel-input.disabled) {
    opacity: 0.5;
    cursor: not-allowed;
}

.nw-phone :deep(.vti__input) {
    background: transparent;
    padding: 8px 10px 8px 4px;
    font-size: 16px;
    line-height: 21px;
    border-radius: 0 8px 8px 0;
    color: var(--color-fg);
}

.nw-phone :deep(.vti__input:focus) {
    outline: none;
}

.nw-phone :deep(.vti__input::placeholder) {
    color: var(--color-faint);
}

.nw-phone :deep(.vti__dropdown) {
    border-radius: 8px 0 0 8px;
    padding: 0 6px 0 10px;
}

.nw-phone :deep(.vti__dropdown:hover),
.nw-phone :deep(.vti__dropdown.open) {
    background: var(--color-line);
}

.nw-phone :deep(.vti__dropdown-list) {
    z-index: 50;
    border: 1px solid var(--color-line);
    border-radius: 8px;
    background: var(--color-surface);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    font-size: 13px;
    padding: 4px;
}

.nw-phone :deep(.vti__dropdown-item) {
    border-radius: 6px;
    padding: 4px 6px;
}

.nw-phone :deep(.vti__dropdown-item.highlighted) {
    background: var(--color-surface-2);
}

.nw-phone :deep(.vti__search_box) {
    width: calc(100% - 8px);
    margin: 4px;
    padding: 6px 8px;
    border: 1px solid var(--color-line);
    border-radius: 7px;
    background: var(--color-surface-2);
    font-size: 16px;
}

@media (min-width: 640px) {
    .nw-phone :deep(.vti__input),
    .nw-phone :deep(.vti__search_box) {
        font-size: 14px;
    }
}
</style>
