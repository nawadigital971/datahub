<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import { index as exportsIndex } from '@/routes/exports';
import type { ExportRow, SelectOption } from '@/types';

const props = defineProps<{
    exports: {
        data: ExportRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { channel: string | null; user: number | null };
    channels: SelectOption<string>[];
    exporters: SelectOption<number>[];
}>();

const dateFormat = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const formatDate = (iso: string | null): string =>
    iso ? dateFormat.format(new Date(iso)) : '—';
const formatNumber = (value: number): string => value.toLocaleString('en-US');
const formatSize = (bytes: number): string =>
    bytes > 1_048_576
        ? `${(bytes / 1_048_576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

function applyFilters(next: { channel?: string | null; user?: number | null }) {
    const filter = { ...props.filters, ...next };
    router.get(
        exportsIndex().url,
        {
            filter: Object.fromEntries(
                Object.entries(filter).filter(([, value]) => value !== null),
            ),
        },
        { preserveState: true, preserveScroll: true },
    );
}

const channel = computed({
    get: () => props.filters.channel,
    set: (value: string | null) => applyFilters({ channel: value }),
});
const user = computed({
    get: () => props.filters.user,
    set: (value: number | null) => applyFilters({ user: value }),
});

const STATUS_CLASSES = {
    queued: 'bg-sand-soft text-sand',
    running: 'bg-sand-soft text-sand',
    done: 'bg-ink-soft text-ink',
    failed: 'bg-bad-soft text-bad',
} as const;

/** Refresh while any export on this page is still being written. */
const { start, stop } = usePoll(
    3000,
    { only: ['exports'] },
    { autoStart: false },
);
watch(
    () => props.exports.data.some((row) => !row.is_finished),
    (running) => (running ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <Head title="Exports" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Export log</h2>
        <p class="m-0 text-[12.5px] text-subtle">
            Every export, who made it and why. Files are kept for a limited
            time; the log is kept forever.
        </p>
    </div>

    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <strong class="num"
                >{{ formatNumber(exports.meta.total) }}
                {{ exports.meta.total === 1 ? 'export' : 'exports' }}</strong
            >
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="w-52">
                    <AppSelect
                        v-model="channel"
                        :options="channels"
                        :searchable="false"
                        placeholder="All channels"
                        size="sm"
                        aria-label="Filter by channel"
                    />
                </div>
                <div class="w-52">
                    <AppSelect
                        v-model="user"
                        :options="exporters"
                        placeholder="Everyone"
                        size="sm"
                        aria-label="Filter by person"
                    />
                </div>
                <button
                    v-if="filters.channel || filters.user"
                    type="button"
                    class="border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-ink"
                    @click="applyFilters({ channel: null, user: null })"
                >
                    Clear
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'When',
                                'Who',
                                'Channel',
                                'Audience',
                                'Rows',
                                'Purpose',
                                'Status',
                                'File',
                            ]"
                            :key="heading"
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ heading }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in exports.data" :key="row.id">
                        <td
                            class="border-t border-line px-3 py-2 align-top whitespace-nowrap num"
                        >
                            {{ formatDate(row.created_at) }}
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            {{ row.user ?? 'Former user' }}
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            {{ row.channel.label }}
                            <div v-if="row.hashed" class="text-xs text-subtle">
                                SHA-256 hashed
                            </div>
                        </td>
                        <td
                            class="max-w-[260px] border-t border-line px-3 py-2 align-top"
                        >
                            <div
                                v-for="part in row.audience"
                                :key="part"
                                class="text-xs"
                                dir="auto"
                            >
                                {{ part }}
                            </div>
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 text-right align-top font-mono num"
                        >
                            {{
                                row.row_count === null
                                    ? '—'
                                    : formatNumber(row.row_count)
                            }}
                        </td>
                        <td
                            class="max-w-[260px] border-t border-line px-3 py-2 align-top"
                            dir="auto"
                        >
                            {{ row.purpose }}
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                :class="STATUS_CLASSES[row.status.key]"
                                >{{ row.status.label }}</span
                            >
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top whitespace-nowrap"
                        >
                            <template v-if="row.file">
                                <a
                                    v-if="row.download_url"
                                    :href="row.download_url"
                                    class="font-medium text-ink"
                                    >Download</a
                                >
                                <div class="text-xs text-subtle">
                                    {{ formatSize(row.file.size) }}
                                    <template v-if="row.downloaded_at">
                                        · downloaded</template
                                    >
                                </div>
                            </template>
                            <span
                                v-else-if="row.status.key === 'done'"
                                class="text-faint"
                                >Expired</span
                            >
                            <span v-else class="text-faint">—</span>
                        </td>
                    </tr>
                    <tr v-if="exports.data.length === 0">
                        <td
                            colspan="8"
                            class="border-t border-line p-[30px] text-center text-subtle"
                        >
                            No exports yet. Start one from the Audience page.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="exports.meta.last_page > 1"
            class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
        >
            <span class="num"
                >Page {{ exports.meta.current_page }} of
                {{ exports.meta.last_page }}</span
            >
            <Link
                v-if="exports.links.prev"
                :href="exports.links.prev"
                preserve-scroll
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Previous</Link
            >
            <Link
                v-if="exports.links.next"
                :href="exports.links.next"
                preserve-scroll
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Next</Link
            >
        </div>
    </div>
</template>
