<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import BatchStatusPill from '@/components/imports/BatchStatusPill.vue';
import EditableCell from '@/components/imports/EditableCell.vue';
import MergePanel from '@/components/imports/MergePanel.vue';
import RowDrawer from '@/components/imports/RowDrawer.vue';
import SummaryBar from '@/components/imports/SummaryBar.vue';
import { index as audienceIndex } from '@/routes/audience';
import {
    commit as commitBatch,
    discard as discardBatch,
    recheck as recheckBatch,
    index as importsIndex,
    show as showBatch,
} from '@/routes/imports';
import { edit as editMapping } from '@/routes/imports/mapping';
import { bulk as bulkRows } from '@/routes/imports/rows';
import type {
    BatchSource,
    ImportBatch,
    ReviewOptions,
    RowUpdateResponse,
    SelectOption,
    StagedRow,
} from '@/types';

const props = defineProps<{
    batch: ImportBatch;
    rows: {
        data: StagedRow[];
        meta: {
            next_cursor: string | null;
            prev_cursor: string | null;
            per_page: number;
        };
    } | null;
    filters: Record<string, string>;
    issueCounts: Record<string, number> | [];
    fileSources: BatchSource[];
    options: ReviewOptions;
}>();

/* ---------- local copies updated by inline edits ---------- */
const batch = ref<ImportBatch>(props.batch);
const rows = ref<StagedRow[]>(props.rows?.data ?? []);
watch(
    () => props.batch,
    (value) => (batch.value = value),
);
watch(
    () => props.rows,
    (value) => (rows.value = value?.data ?? []),
);

function applyUpdate(response: RowUpdateResponse): void {
    batch.value = response.batch;
    rows.value = rows.value.map((row) =>
        row.id === response.row.id ? response.row : row,
    );
}

/* ---------- busy states poll until the background job is done ---------- */
const { start, stop } = usePoll(
    2000,
    { only: ['batch', 'rows', 'issueCounts'] },
    { autoStart: false },
);
watch(
    () => batch.value.is_busy,
    (busy) => (busy ? start() : stop()),
    { immediate: true },
);

/* ---------- filters ---------- */
const filterValues = (key: string): string[] =>
    (props.filters[key] ?? '').split(',').filter((value) => value !== '');

function visit(filters: Record<string, string[]>): void {
    const filter = Object.fromEntries(
        Object.entries(filters)
            .filter(([, values]) => values.length > 0)
            .map(([key, values]) => [key, values.join(',')]),
    );
    router.get(
        showBatch(batch.value.id).url,
        { filter },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'filters', 'batch'],
        },
    );
    selected.value = [];
}

const currentFilters = computed<Record<string, string[]>>(() => ({
    issue: filterValues('issue'),
    match: filterValues('match'),
    action: filterValues('action'),
    severity: filterValues('severity'),
}));

const setFilter = (key: string, values: string[]): void =>
    visit({ ...currentFilters.value, [key]: values });

const issueOptions = computed<SelectOption<string>[]>(() =>
    props.options.issues.map((issue) => ({
        ...issue,
        hint: (props.issueCounts as Record<string, number>)[issue.value] ?? 0,
    })),
);

const activeTile = computed(() => {
    const f = currentFilters.value;
    const only = (key: string, value: string): boolean =>
        f[key].length === 1 &&
        f[key][0] === value &&
        Object.entries(f).every(([k, v]) => k === key || v.length === 0);
    if (Object.values(f).every((v) => v.length === 0)) return 'total';
    if (only('severity', 'clean')) return 'clean';
    if (only('severity', 'warning')) return 'warnings';
    if (only('severity', 'error')) return 'errors';
    if (only('match', 'new')) return 'new';
    if (only('match', 'matches_contact')) return 'merge';
    if (only('match', 'dup_in_batch')) return 'dup';
    if (only('match', 'conflict')) return 'conflict';
    if (only('action', 'skip')) return 'skipped';

    return null;
});

function pickTile(filter: { key: string; value: string } | null): void {
    visit(filter ? { [filter.key]: [filter.value] } : {});
}

const hasFilters = computed(() =>
    Object.values(currentFilters.value).some((v) => v.length > 0),
);

/* ---------- selection + bulk ---------- */
const selected = ref<number[]>([]);
const allMatching = ref(false);
const allOnPageSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selected.value.includes(row.id)),
);

function toggleAll(): void {
    selected.value = allOnPageSelected.value
        ? []
        : rows.value.map((row) => row.id);
    allMatching.value = false;
}

const OPERATIONS: SelectOption<string>[] = [
    { value: 'accept_suggestions', label: 'Accept all suggestions' },
    { value: 'set_action', label: 'Set action' },
    { value: 'set_region', label: 'Set emirate' },
    { value: 'set_area', label: 'Set area' },
    { value: 'set_sector', label: 'Set sector' },
    { value: 'set_source', label: 'Set source' },
];
const operation = ref<string | null>('accept_suggestions');
const operationValue = ref<string | number | null>(null);
watch(operation, () => (operationValue.value = null));

const valueOptions = computed<SelectOption<string | number>[] | null>(() => {
    switch (operation.value) {
        case 'set_action':
            return props.options.actions;
        case 'set_region':
            return props.options.regions;
        case 'set_area':
            return props.options.areas;
        case 'set_sector':
            return props.options.sectors;
        default:
            return null;
    }
});

const bulkReady = computed(
    () =>
        operation.value !== null &&
        (selected.value.length > 0 || allMatching.value) &&
        (operation.value === 'set_source'
            ? typeof operationValue.value === 'string' &&
              operationValue.value.trim() !== ''
            : valueOptions.value === null || operationValue.value !== null),
);

function applyBulk(): void {
    if (!bulkReady.value) {
        return;
    }
    router.post(
        bulkRows(batch.value.id).url,
        {
            operation: operation.value,
            value: operationValue.value,
            ...(allMatching.value
                ? { all_matching: true, filter: props.filters }
                : { ids: selected.value }),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                selected.value = [];
                allMatching.value = false;
            },
        },
    );
}

/* ---------- row helpers ---------- */
const issueLabel = (code: string): string =>
    props.options.issues.find((issue) => issue.value === code)?.label ?? code;

const areaOptionsFor = (row: StagedRow): SelectOption<number>[] =>
    props.options.areas.filter(
        (area) => row.region === null || area.region_id === row.region.id,
    );

const actionOptionsFor = (row: StagedRow): SelectOption<string>[] =>
    props.options.actions.map((action) => ({
        ...action,
        disabled: action.value === 'merge' && row.match.contact_id === null,
    }));

const SEVERITY_DOT = {
    clean: 'bg-good',
    warning: 'bg-warn',
    error: 'bg-bad',
} as const;

const drawerRow = ref<StagedRow | null>(null);
const drawerOpen = ref(false);
function openDrawer(row: StagedRow): void {
    drawerRow.value = row;
    drawerOpen.value = true;
}

const mergeRow = ref<StagedRow | null>(null);
const mergeOpen = ref(false);
function openMerge(row: StagedRow): void {
    mergeRow.value = row;
    mergeOpen.value = true;
}

/* ---------- paging ---------- */
function goToCursor(cursor: string | null): void {
    if (!cursor) return;
    router.get(
        showBatch(batch.value.id).url,
        { filter: props.filters, cursor },
        { preserveState: true, preserveScroll: true, only: ['rows'] },
    );
}

/* ---------- commit / discard ---------- */
const confirming = ref<'commit' | 'discard' | 'recheck' | null>(null);
const willCreate = computed(
    () =>
        batch.value.counts.total -
        batch.value.counts.skipped -
        batch.value.counts.merge,
);

function commit(): void {
    router.post(
        commitBatch(batch.value.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (confirming.value = null),
        },
    );
}

function recheck(): void {
    router.post(
        recheckBatch(batch.value.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (confirming.value = null),
        },
    );
}

function discard(): void {
    router.post(discardBatch(batch.value.id).url);
}

const format = (value: number): string => value.toLocaleString('en-US');
const dateFormat = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const formatDate = (iso: string | null): string =>
    iso ? dateFormat.format(new Date(iso)) : '—';
</script>

<template>
    <Head :title="`Import · ${batch.filename ?? ''}`" />

    <div class="mb-3.5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <Link
                :href="importsIndex()"
                class="text-[12.5px] font-medium text-ink"
                >← Imports</Link
            >
            <h2
                class="m-0 mt-1 flex flex-wrap items-center gap-2 font-display text-base font-semibold"
            >
                <bdi class="truncate">{{ batch.filename }}</bdi>
                <BatchStatusPill :status="batch.status" />
            </h2>
            <p class="m-0 text-[12.5px] text-subtle">
                {{ batch.source }} · uploaded
                {{ formatDate(batch.created_at) }} by {{ batch.user }}
                <template v-if="batch.mapping"> · {{ batch.mapping }}</template>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Link
                v-if="batch.can.map"
                :href="editMapping(batch.id)"
                class="rounded-lg border border-line px-3.5 py-2 text-[13px] font-semibold text-fg no-underline"
                >Change mapping</Link
            >
            <button
                v-if="batch.can.recheck"
                type="button"
                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                @click="confirming = 'recheck'"
            >
                Re-check
            </button>
            <button
                v-if="batch.can.discard"
                type="button"
                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-bad"
                @click="confirming = 'discard'"
            >
                Discard
            </button>
            <button
                v-if="batch.can.commit"
                type="button"
                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                @click="confirming = 'commit'"
            >
                {{ batch.status.key === 'failed' ? 'Resume commit' : 'Commit' }}
            </button>
        </div>
    </div>

    <!-- confirm steps (inside the page, as the brief asks) -->
    <div
        v-if="confirming === 'commit'"
        class="mb-3.5 rounded-card border border-ink bg-surface px-4 py-3.5 text-[13px]"
        role="alertdialog"
        aria-labelledby="commit-title"
    >
        <h3 id="commit-title" class="m-0 mb-1 text-[13.5px] font-semibold">
            Commit this import?
        </h3>
        <p class="m-0 mb-3 text-subtle">
            Creates about
            <b class="text-fg num">{{ format(Math.max(0, willCreate)) }}</b> new
            contacts, merges
            <b class="text-fg num">{{ format(batch.counts.merge) }}</b> into
            existing ones and skips
            <b class="text-fg num">{{ format(batch.counts.skipped) }}</b> rows.
            Numbers and emails on the do-not-contact list are never imported.
            This cannot be undone.
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                @click="commit"
            >
                Commit now
            </button>
            <button
                type="button"
                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold"
                @click="confirming = null"
            >
                Cancel
            </button>
        </div>
    </div>
    <div
        v-if="confirming === 'recheck'"
        class="mb-3.5 rounded-card border border-ink bg-surface px-4 py-3.5 text-[13px]"
        role="alertdialog"
        aria-labelledby="recheck-title"
    >
        <h3 id="recheck-title" class="m-0 mb-1 text-[13.5px] font-semibold">
            Re-check with the current rules?
        </h3>
        <p class="m-0 mb-3 text-subtle">
            Every row is analysed again with today's cleaning rules, areas and
            do-not-contact list. Edits made in this preview and merge choices
            are lost.
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                @click="recheck"
            >
                Re-check now
            </button>
            <button
                type="button"
                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold"
                @click="confirming = null"
            >
                Cancel
            </button>
        </div>
    </div>
    <div
        v-if="confirming === 'discard'"
        class="mb-3.5 rounded-card border border-bad bg-surface px-4 py-3.5 text-[13px]"
        role="alertdialog"
        aria-labelledby="discard-title"
    >
        <h3 id="discard-title" class="m-0 mb-1 text-[13.5px] font-semibold">
            Discard this import?
        </h3>
        <p class="m-0 mb-3 text-subtle">
            Nothing will be imported. The rows are deleted after the retention
            period.
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                class="rounded-lg border-0 bg-bad px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                @click="discard"
            >
                Discard import
            </button>
            <button
                type="button"
                class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold"
                @click="confirming = null"
            >
                Keep it
            </button>
        </div>
    </div>

    <!-- states without a reviewable table -->
    <div
        v-if="batch.is_busy"
        class="rounded-card border border-line bg-surface px-4 py-6 text-[13px] text-subtle"
        role="status"
    >
        <span
            class="mr-2 inline-block size-3 animate-pulse rounded-full bg-sand align-middle"
        ></span>
        {{
            batch.status.key === 'committing'
                ? 'Committing… contacts are being created and merged. This page updates by itself.'
                : 'Analysing the file… phones, emails, areas and duplicates are being checked. This page updates by itself.'
        }}
    </div>
    <div
        v-else-if="batch.status.key === 'committed'"
        class="rounded-card border border-line bg-surface px-4 py-4 text-[13px]"
    >
        <h3 class="m-0 mb-1 text-[13.5px] font-semibold">
            Committed {{ formatDate(batch.committed_at) }}
        </h3>
        <p class="m-0 mb-3 text-subtle">
            {{ format(batch.counts.total) }} rows checked:
            {{ format(batch.counts.new) }} new,
            {{ format(batch.counts.merge) }} matched existing contacts,
            {{ format(batch.counts.skipped) }} skipped. The audience counts are
            refreshed.
        </p>
        <Link :href="audienceIndex()" class="font-medium text-ink"
            >Open the audience</Link
        >
    </div>
    <div
        v-else-if="batch.status.key === 'discarded'"
        class="rounded-card border border-line bg-surface px-4 py-4 text-[13px] text-subtle"
    >
        This import was discarded. Nothing from it reached the contacts.
    </div>
    <div
        v-if="batch.status.key === 'failed' && batch.error"
        class="mb-3.5 rounded-[9px] bg-bad-soft px-3 py-2.5 text-[12.5px] text-bad"
        role="alert"
    >
        {{ batch.error }}
    </div>

    <!-- review -->
    <div v-if="rows !== null && props.rows" class="flex flex-col gap-3.5">
        <SummaryBar
            :counts="batch.counts"
            :active="activeTile"
            @pick="pickTile"
        />

        <div
            v-if="fileSources.length"
            class="rounded-card border border-line bg-surface px-4 py-3 text-[12.5px]"
        >
            <h3 class="m-0 mb-2 text-[13.5px] font-semibold">
                Sources in this file
            </h3>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="source in fileSources"
                    :key="source.name"
                    class="inline-flex items-center gap-2 rounded-lg border px-2.5 py-1.5"
                    :class="
                        source.existing
                            ? 'border-line'
                            : 'border-dashed border-sand bg-sand-soft'
                    "
                >
                    <b dir="auto" class="font-semibold">{{ source.name }}</b>
                    <span class="font-mono text-subtle num">{{
                        format(source.rows)
                    }}</span>
                    <span
                        class="text-[11px]"
                        :class="source.existing ? 'text-subtle' : 'text-sand'"
                        >{{
                            source.existing
                                ? 'existing'
                                : 'new · created on commit'
                        }}{{
                            source.default ? ' · rows without a source' : ''
                        }}</span
                    >
                </span>
            </div>
        </div>

        <div
            class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
        >
            <div
                class="flex flex-wrap items-center gap-2.5 border-b border-line px-4 py-3"
            >
                <div class="w-60">
                    <AppSelect
                        :model-value="currentFilters.issue"
                        :options="issueOptions"
                        multiple
                        placeholder="Any problem"
                        size="sm"
                        aria-label="Filter by problem"
                        @update:model-value="
                            setFilter('issue', ($event as string[]) ?? [])
                        "
                    />
                </div>
                <div class="w-48">
                    <AppSelect
                        :model-value="currentFilters.match"
                        :options="options.matches"
                        multiple
                        :searchable="false"
                        placeholder="Any match"
                        size="sm"
                        aria-label="Filter by match"
                        @update:model-value="
                            setFilter('match', ($event as string[]) ?? [])
                        "
                    />
                </div>
                <div class="w-44">
                    <AppSelect
                        :model-value="currentFilters.action"
                        :options="options.actions"
                        multiple
                        :searchable="false"
                        placeholder="Any action"
                        size="sm"
                        aria-label="Filter by action"
                        @update:model-value="
                            setFilter('action', ($event as string[]) ?? [])
                        "
                    />
                </div>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-ink"
                    @click="visit({})"
                >
                    Clear filters
                </button>
            </div>

            <div
                v-if="batch.can.review"
                class="flex flex-wrap items-center gap-2.5 border-b border-line bg-surface-2 px-4 py-2.5 text-[12.5px]"
            >
                <span v-if="allMatching" class="font-medium"
                    >All rows matching the filters</span
                >
                <span v-else class="font-medium num"
                    >{{ format(selected.length) }} selected</span
                >
                <button
                    v-if="selected.length > 0 && !allMatching"
                    type="button"
                    class="border-0 bg-transparent px-1 text-[12.5px] font-medium text-ink"
                    @click="allMatching = true"
                >
                    Select all matching rows
                </button>
                <div class="w-52">
                    <AppSelect
                        v-model="operation"
                        :options="OPERATIONS"
                        :searchable="false"
                        size="sm"
                        aria-label="Bulk action"
                    />
                </div>
                <input
                    v-if="operation === 'set_source'"
                    v-model="operationValue"
                    class="w-52 rounded-[7px] border border-line bg-surface p-1.5 text-base sm:text-sm"
                    placeholder="Source name"
                    maxlength="120"
                    dir="auto"
                    aria-label="Source name"
                />
                <div v-if="valueOptions" class="w-52">
                    <AppSelect
                        v-model="operationValue"
                        :options="valueOptions"
                        size="sm"
                        placeholder="Choose…"
                        aria-label="Bulk value"
                    />
                </div>
                <button
                    type="button"
                    class="rounded-lg border-0 bg-ink px-3 py-1.5 text-[12.5px] font-semibold text-ink-fg disabled:opacity-40"
                    :disabled="!bulkReady"
                    @click="applyBulk"
                >
                    Apply
                </button>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[1440px] border-collapse text-[12.5px]"
                >
                    <thead>
                        <tr>
                            <th class="w-8 bg-surface-2 px-3 py-[9px]">
                                <input
                                    v-if="batch.can.review"
                                    type="checkbox"
                                    class="accent-ink"
                                    aria-label="Select all rows on this page"
                                    :checked="allOnPageSelected"
                                    @change="toggleAll"
                                />
                            </th>
                            <th
                                v-for="heading in [
                                    'Row',
                                    'Problems',
                                    'Source',
                                    'English name',
                                    'Arabic name',
                                    'Company',
                                    'Mobile',
                                    'Landline',
                                    'Email',
                                    'Emirate',
                                    'Area',
                                    'Match',
                                    'Action',
                                ]"
                                :key="heading"
                                class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                            >
                                {{ heading }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            :class="{
                                'bg-surface-2/60': selected.includes(row.id),
                            }"
                        >
                            <td
                                class="border-t border-line px-3 py-1.5 align-top"
                            >
                                <input
                                    v-if="batch.can.review"
                                    v-model="selected"
                                    type="checkbox"
                                    class="mt-1.5 accent-ink"
                                    :value="row.id"
                                    :aria-label="`Select row ${row.row_number}`"
                                    @change="allMatching = false"
                                />
                            </td>
                            <td
                                class="border-t border-line px-3 py-1.5 align-top whitespace-nowrap"
                            >
                                <button
                                    type="button"
                                    class="mt-1 inline-flex items-center gap-1.5 border-0 bg-transparent p-0 font-mono text-ink"
                                    :aria-label="`Open row ${row.row_number}`"
                                    @click="openDrawer(row)"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :class="SEVERITY_DOT[row.severity]"
                                        aria-hidden="true"
                                    ></span>
                                    {{ row.row_number }}
                                </button>
                            </td>
                            <td
                                class="max-w-[220px] border-t border-line px-3 py-1.5 align-top"
                            >
                                <span class="mt-1 inline-flex flex-wrap gap-1">
                                    <span
                                        v-for="(issue, index) in row.issues"
                                        :key="index"
                                        class="inline-block rounded-full px-[7px] py-px text-[11px] font-medium whitespace-nowrap"
                                        :class="
                                            issue.severity === 2
                                                ? 'bg-bad-soft text-bad'
                                                : 'bg-sand-soft text-sand'
                                        "
                                        :title="
                                            issue.message +
                                            (issue.suggestion
                                                ? ` → ${issue.suggestion}`
                                                : '')
                                        "
                                        >{{ issueLabel(issue.code) }}</span
                                    >
                                    <span
                                        v-if="row.issues.length === 0"
                                        class="inline-block rounded-full bg-ink-soft px-[7px] py-px text-[11px] font-medium text-ink"
                                        >Clean</span
                                    >
                                </span>
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="source_name"
                                    :value="row.source_name"
                                    :display="row.source_name ?? batch.source"
                                    label="Source"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="name_en"
                                    :value="row.name_en"
                                    label="English name"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="name_ar"
                                    :value="row.name_ar"
                                    label="Arabic name"
                                    dir="rtl"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="company_name"
                                    :value="row.company_name"
                                    label="Company"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="mobile_e164"
                                    :value="row.mobile_e164"
                                    label="Mobile"
                                    dir="ltr"
                                    mono
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="landline_e164"
                                    :value="row.landline_e164"
                                    label="Landline"
                                    dir="ltr"
                                    mono
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="email"
                                    :value="row.email"
                                    label="Email"
                                    dir="ltr"
                                    mono
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="w-40 border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="region_id"
                                    :value="row.region?.id ?? null"
                                    :options="options.regions"
                                    label="Emirate"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="w-44 border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="area_id"
                                    :value="row.area?.id ?? null"
                                    :options="areaOptionsFor(row)"
                                    label="Area"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                            <td
                                class="border-t border-line px-3 py-1.5 align-top whitespace-nowrap"
                            >
                                <div class="mt-1">{{ row.match.label }}</div>
                                <div
                                    v-if="
                                        row.match.status === 'dup_in_batch' &&
                                        row.match.dup_of_row
                                    "
                                    class="text-xs text-subtle"
                                >
                                    same as row {{ row.match.dup_of_row }}
                                </div>
                                <button
                                    v-if="
                                        batch.can.review &&
                                        (row.match.status ===
                                            'matches_contact' ||
                                            row.match.status === 'conflict')
                                    "
                                    type="button"
                                    class="border-0 bg-transparent p-0 text-xs font-medium text-ink"
                                    @click="openMerge(row)"
                                >
                                    {{
                                        row.has_merge_choices
                                            ? 'Review merge ✓'
                                            : 'Compare & merge'
                                    }}
                                </button>
                            </td>
                            <td
                                class="w-36 border-t border-line px-1.5 py-1.5 align-top"
                            >
                                <EditableCell
                                    :batch-id="batch.id"
                                    :row-id="row.id"
                                    field="action"
                                    :value="row.action"
                                    :options="actionOptionsFor(row)"
                                    label="Action"
                                    :disabled="!batch.can.review"
                                    @updated="applyUpdate"
                                />
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                colspan="14"
                                class="border-t border-line p-[30px] text-center text-subtle"
                            >
                                No rows match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
            >
                <button
                    type="button"
                    class="rounded-md border border-line bg-surface px-2.5 py-1 disabled:opacity-40"
                    :disabled="!props.rows.meta.prev_cursor"
                    @click="goToCursor(props.rows.meta.prev_cursor)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="rounded-md border border-line bg-surface px-2.5 py-1 disabled:opacity-40"
                    :disabled="!props.rows.meta.next_cursor"
                    @click="goToCursor(props.rows.meta.next_cursor)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>

    <RowDrawer v-model:open="drawerOpen" :row="drawerRow" />
    <MergePanel
        v-model:open="mergeOpen"
        :batch-id="batch.id"
        :row="mergeRow"
        @updated="applyUpdate"
    />
</template>
