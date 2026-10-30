<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import BatchStatusPill from '@/components/imports/BatchStatusPill.vue';
import { usePermissions } from '@/composables/usePermissions';
import { fieldClasses } from '@/lib/fieldClasses';
import { edit as editMapping } from '@/routes/imports/mapping';
import { show as showBatch, store as storeBatch } from '@/routes/imports';
import type { ImportBatch, SelectOption } from '@/types';

const props = defineProps<{
    batches: {
        data: ImportBatch[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    sources: SelectOption<number>[];
}>();

const { can } = usePermissions();

const form = useForm<{
    source_id: number | null;
    source_name: string;
    file: File | null;
}>({
    source_id: null,
    source_name: '',
    file: null,
});
const fileInput = ref<HTMLInputElement | null>(null);

function pickFile(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    form.post(storeBatch().url, { forceFormData: true });
}

const formatNumber = (value: number): string => value.toLocaleString('en-US');
const dateFormat = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const formatDate = (iso: string | null): string =>
    iso ? dateFormat.format(new Date(iso)) : '—';

const openUrl = (batch: ImportBatch) =>
    batch.status.key === 'mapping'
        ? editMapping(batch.id)
        : showBatch(batch.id);

/** Refresh while any batch is being analyzed or committed. */
const { start, stop } = usePoll(
    2500,
    { only: ['batches'] },
    { autoStart: false },
);
const anyBusy = computed(() =>
    props.batches.data.some((batch) => batch.is_busy),
);
watch(anyBusy, (busy) => (busy ? start() : stop()), { immediate: true });
</script>

<template>
    <Head title="Imports" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Imports</h2>
        <p class="m-0 text-[12.5px] text-subtle">
            Upload a file, check every row in the preview, then commit. Nothing
            reaches the contacts until a person commits it.
        </p>
    </div>

    <form
        v-if="can('imports.create')"
        class="mb-[18px] rounded-card border border-line bg-surface px-4 py-3.5"
        @submit.prevent="upload"
    >
        <h3 class="m-0 mb-3 text-[13.5px] font-semibold">New import</h3>
        <div
            class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto] items-end gap-3.5 max-[900px]:grid-cols-1"
        >
            <FormField
                v-slot="field"
                label="Source"
                hint="An existing list, or type a new name. Leave empty if the file has a Source column; otherwise the file name is used."
                :error="form.errors.source_id ?? form.errors.source_name"
            >
                <div class="flex gap-2">
                    <div v-if="sources.length" class="min-w-0 flex-1">
                        <AppSelect
                            :id="field.id"
                            v-model="form.source_id"
                            :options="sources"
                            placeholder="Existing source"
                            :invalid="field.invalid"
                            :described-by="field.describedBy"
                            @update:model-value="form.source_name = ''"
                        />
                    </div>
                    <input
                        v-model="form.source_name"
                        :class="[fieldClasses, 'min-w-0 flex-1']"
                        :placeholder="
                            sources.length
                                ? 'or a new source name'
                                : 'New source name'
                        "
                        maxlength="120"
                        dir="auto"
                        aria-label="New source name"
                        @input="form.source_id = null"
                    />
                </div>
            </FormField>
            <FormField
                v-slot="field"
                label="File"
                required
                hint="Excel (.xlsx, .xls), CSV, text, .ods or Word (.docx) · up to 100 MB"
                :error="form.errors.file"
            >
                <input
                    :id="field.id"
                    ref="fileInput"
                    type="file"
                    accept=".xlsx,.xls,.csv,.txt,.ods,.docx"
                    required
                    aria-required="true"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                    :class="[
                        fieldClasses,
                        'file:mr-3 file:rounded-md file:border-0 file:bg-ink-soft file:px-2.5 file:py-1 file:text-[12.5px] file:font-semibold file:text-ink',
                    ]"
                    @change="pickFile"
                />
            </FormField>
            <button
                type="submit"
                class="h-[39px] rounded-lg border-0 bg-ink px-3.5 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                :disabled="form.processing || !form.file"
            >
                {{ form.processing ? 'Uploading…' : 'Upload and map columns' }}
            </button>
        </div>
        <div
            v-if="form.progress"
            class="mt-3 h-1 overflow-hidden rounded-full bg-surface-2"
            role="progressbar"
            :aria-valuenow="form.progress.percentage"
        >
            <i
                class="block h-full bg-ink"
                :style="{ width: `${form.progress.percentage}%` }"
            ></i>
        </div>
    </form>

    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div
            class="flex items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <strong class="num"
                >{{ formatNumber(batches.meta.total) }}
                {{ batches.meta.total === 1 ? 'import' : 'imports' }}</strong
            >
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'File',
                                'Source',
                                'Uploaded',
                                'Status',
                                'Rows',
                                'New',
                                'Merge',
                                'Skip',
                                'Problems',
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
                    <tr v-for="batch in batches.data" :key="batch.id">
                        <td
                            class="max-w-[240px] truncate border-t border-line px-3 py-2"
                            dir="auto"
                            :title="batch.filename ?? ''"
                        >
                            {{ batch.filename }}
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            {{ batch.source }}
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            {{ formatDate(batch.created_at) }}
                            <div class="text-xs text-subtle">
                                {{ batch.user }}
                            </div>
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            <BatchStatusPill :status="batch.status" />
                        </td>
                        <td
                            v-for="(value, index) in [
                                batch.counts.total,
                                batch.counts.new,
                                batch.counts.merge,
                                batch.counts.skipped,
                            ]"
                            :key="index"
                            class="border-t border-line px-3 py-2 text-right font-mono num"
                        >
                            {{ formatNumber(value) }}
                        </td>
                        <td class="border-t border-line px-3 py-2 text-xs">
                            <span v-if="batch.counts.errors" class="text-bad"
                                >{{
                                    formatNumber(batch.counts.errors)
                                }}
                                errors</span
                            >
                            <span
                                v-if="batch.counts.warnings"
                                class="ml-1 text-warn"
                                >{{
                                    formatNumber(batch.counts.warnings)
                                }}
                                warnings</span
                            >
                        </td>
                        <td class="border-t border-line px-3 py-2 text-right">
                            <Link
                                :href="openUrl(batch)"
                                class="font-medium whitespace-nowrap text-ink"
                                >{{
                                    batch.status.key === 'mapping'
                                        ? 'Map columns'
                                        : 'Open'
                                }}</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="batches.data.length === 0">
                        <td
                            colspan="10"
                            class="border-t border-line p-[30px] text-center text-subtle"
                        >
                            No imports yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="batches.meta.last_page > 1"
            class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
        >
            <span class="num"
                >Page {{ batches.meta.current_page }} of
                {{ batches.meta.last_page }}</span
            >
            <Link
                v-if="batches.links.prev"
                :href="batches.links.prev"
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Previous</Link
            >
            <Link
                v-if="batches.links.next"
                :href="batches.links.next"
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Next</Link
            >
        </div>
    </div>
</template>
