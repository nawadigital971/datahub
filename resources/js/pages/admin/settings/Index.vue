<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import Toggle from '@/components/Toggle.vue';
import { fieldClasses } from '@/lib/fieldClasses';
import { update as updateData } from '@/routes/admin/settings/data';
import { update as updateGeneral } from '@/routes/admin/settings/general';
import type { SelectOption } from '@/types';

type AssetKey = 'logo_light' | 'logo_dark' | 'favicon';

const props = defineProps<{
    general: {
        site_name: Record<string, string>;
        default_theme: string;
        assets: Record<AssetKey, string | null>;
    };
    data: {
        stale_data_year_threshold: number;
        hash_on_export_default: boolean;
        export_retention_days: number;
        staged_retention_days: number;
        default_phone_region: string;
    };
    countries: SelectOption<string>[];
    themes: SelectOption<string>[];
    locales: string[];
}>();

const general = useForm({
    site_name: { ...props.general.site_name } as Record<string, string>,
    default_theme: props.general.default_theme,
    logo_light: null as File | null,
    logo_dark: null as File | null,
    favicon: null as File | null,
    remove: [] as AssetKey[],
});

const dataForm = useForm({ ...props.data });

const ASSETS: { key: AssetKey; label: string; hint: string; accept: string }[] =
    [
        {
            key: 'logo_light',
            label: 'Logo',
            hint: 'PNG, JPG, WebP or SVG, up to 2 MB. Shown in the top bar.',
            accept: 'image/png,image/jpeg,image/webp,image/svg+xml',
        },
        {
            key: 'logo_dark',
            label: 'Logo for dark backgrounds',
            hint: 'Used when a dark theme is added.',
            accept: 'image/png,image/jpeg,image/webp,image/svg+xml',
        },
        {
            key: 'favicon',
            label: 'Favicon',
            hint: 'PNG, ICO or SVG, up to 512 KB. Kept as uploaded (never converted to WebP).',
            accept: 'image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml',
        },
    ];

const LOCALE_LABELS: Record<string, string> = { en: 'English', ar: 'Arabic' };

function pick(key: AssetKey, event: Event): void {
    general[key] = (event.target as HTMLInputElement).files?.[0] ?? null;
    general.remove = general.remove.filter((item) => item !== key);
}

function remove(key: AssetKey): void {
    general[key] = null;
    if (!general.remove.includes(key)) {
        general.remove.push(key);
    }
}

function saveGeneral(): void {
    general.post(updateGeneral().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () =>
            general.reset('logo_light', 'logo_dark', 'favicon', 'remove'),
    });
}

function saveData(): void {
    dataForm.put(updateData().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Settings</h2>
    </div>

    <div
        class="grid grid-cols-2 items-start gap-[18px] max-[900px]:grid-cols-1"
    >
        <form
            class="flex flex-col gap-3.5 rounded-card border border-line bg-surface px-4 py-3.5"
            @submit.prevent="saveGeneral"
        >
            <h3 class="m-0 text-[13.5px] font-semibold">General</h3>

            <FormField
                v-for="locale in locales"
                :key="locale"
                v-slot="field"
                :label="`Site name (${LOCALE_LABELS[locale] ?? locale})`"
                :required="locale === 'en'"
                :error="general.errors[`site_name.${locale}`]"
            >
                <input
                    :id="field.id"
                    v-model="general.site_name[locale]"
                    :class="fieldClasses"
                    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
                    :lang="locale"
                    maxlength="80"
                    :required="locale === 'en'"
                    :aria-required="locale === 'en'"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>

            <FormField
                v-slot="field"
                label="Default theme"
                required
                hint="The app is light-only for now; this applies once dark mode is added."
                :error="general.errors.default_theme"
            >
                <AppSelect
                    :id="field.id"
                    v-model="general.default_theme"
                    :options="themes"
                    :searchable="false"
                    :required="true"
                    :described-by="field.describedBy"
                />
            </FormField>

            <FormField
                v-for="asset in ASSETS"
                :key="asset.key"
                v-slot="field"
                :label="asset.label"
                :hint="asset.hint"
                :error="general.errors[asset.key]"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-12 w-28 flex-none place-items-center overflow-hidden rounded-lg border border-line bg-surface-2"
                    >
                        <img
                            v-if="
                                props.general.assets[asset.key] &&
                                !general.remove.includes(asset.key)
                            "
                            :src="props.general.assets[asset.key] ?? ''"
                            :alt="`Current ${asset.label.toLowerCase()}`"
                            class="max-h-10 max-w-24 object-contain"
                        />
                        <span v-else class="text-[11px] text-faint">None</span>
                    </div>
                    <input
                        :id="field.id"
                        type="file"
                        :accept="asset.accept"
                        :aria-describedby="field.describedBy"
                        :aria-invalid="field.invalid"
                        :class="[
                            fieldClasses,
                            'file:mr-3 file:rounded-md file:border-0 file:bg-ink-soft file:px-2.5 file:py-1 file:text-[12.5px] file:font-semibold file:text-ink',
                        ]"
                        @change="pick(asset.key, $event)"
                    />
                    <button
                        v-if="
                            props.general.assets[asset.key] &&
                            !general.remove.includes(asset.key)
                        "
                        type="button"
                        class="border-0 bg-transparent px-1 text-[12.5px] font-medium text-bad"
                        @click="remove(asset.key)"
                    >
                        Remove
                    </button>
                </div>
            </FormField>

            <div>
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="general.processing"
                >
                    Save general settings
                </button>
            </div>
        </form>

        <form
            class="flex flex-col gap-3.5 rounded-card border border-line bg-surface px-4 py-3.5"
            @submit.prevent="saveData"
        >
            <h3 class="m-0 text-[13.5px] font-semibold">Data rules</h3>

            <FormField
                v-slot="field"
                label="Stale before year"
                required
                hint="Contacts last seen before this year are flagged as stale and greyed in the data-age chart."
                :error="dataForm.errors.stale_data_year_threshold"
            >
                <input
                    :id="field.id"
                    v-model.number="dataForm.stale_data_year_threshold"
                    type="number"
                    min="2000"
                    :max="new Date().getFullYear() + 1"
                    required
                    aria-required="true"
                    :class="fieldClasses"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>

            <Toggle
                v-model="dataForm.hash_on_export_default"
                label="Hash Meta and Google exports by default (SHA-256)"
            />

            <FormField
                v-slot="field"
                label="Keep export files for (days)"
                required
                hint="Files are deleted after this; the export log is kept forever."
                :error="dataForm.errors.export_retention_days"
            >
                <input
                    :id="field.id"
                    v-model.number="dataForm.export_retention_days"
                    type="number"
                    min="1"
                    max="3650"
                    required
                    aria-required="true"
                    :class="fieldClasses"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>

            <FormField
                v-slot="field"
                label="Keep discarded imports for (days)"
                required
                hint="Staged rows and files of discarded or failed imports are deleted after this."
                :error="dataForm.errors.staged_retention_days"
            >
                <input
                    :id="field.id"
                    v-model.number="dataForm.staged_retention_days"
                    type="number"
                    min="1"
                    max="365"
                    required
                    aria-required="true"
                    :class="fieldClasses"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>

            <FormField
                v-slot="field"
                label="Country for local phone numbers"
                required
                hint="Numbers written without a country code (e.g. 050 123 4567) are read as numbers from this country."
                :error="dataForm.errors.default_phone_region"
            >
                <AppSelect
                    :id="field.id"
                    v-model="dataForm.default_phone_region"
                    :options="countries"
                    :required="true"
                    :invalid="field.invalid"
                    :described-by="field.describedBy"
                />
            </FormField>

            <div>
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="dataForm.processing"
                >
                    Save data rules
                </button>
            </div>
        </form>
    </div>
</template>
