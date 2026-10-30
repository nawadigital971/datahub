<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import Toggle from '@/components/Toggle.vue';
import { usePermissions } from '@/composables/usePermissions';
import { fieldClasses } from '@/lib/fieldClasses';
import { http } from '@/lib/http';
import {
    index as exportsIndex,
    preview as exportPreview,
    show as exportShow,
    store as storeExport,
} from '@/routes/exports';
import type { ChannelKey, ExportRow } from '@/types';

const props = defineProps<{
    channel: ChannelKey;
    filters: Record<string, string>;
    channelCounts: Record<ChannelKey, number> | null;
    hashByDefault: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const { can } = usePermissions();

const FORMATS: {
    channel: ChannelKey;
    title: string;
    description: string;
    hashable?: boolean;
    note: string;
}[] = [
    {
        channel: 'email',
        title: 'Email platform',
        description: 'Mailchimp / Brevo columns',
        note: '<b>Before sending:</b> run these addresses through a verification service. Lists from 2013–2016 will bounce heavily and can damage your sending domain.',
    },
    {
        channel: 'whatsapp',
        title: 'WhatsApp',
        description: 'E.164 phone + name',
        note: '<b>WhatsApp rule:</b> the Business API only allows template messages to people who opted in. Cold sends to old lists get numbers banned quickly.',
    },
    {
        channel: 'sms',
        title: 'SMS gateway',
        description: 'UAE mobiles only',
        note: '<b>UAE SMS:</b> promotional SMS must go through a TDRA-registered sender ID and respect quiet hours.',
    },
    {
        channel: 'meta',
        title: 'Meta Custom Audience',
        description: 'SHA-256 hashed keys',
        hashable: true,
        note: '<b>Hashed on export:</b> email, phone, names, city and country are normalized and SHA-256 hashed, which is the format Meta asks for.',
    },
    {
        channel: 'google',
        title: 'Google Customer Match',
        description: 'Hashed Email + Phone',
        hashable: true,
        note: '<b>Hashed on export:</b> Google Customer Match accepts SHA-256 hashed email, phone (E.164) and names. Country stays plain.',
    },
    {
        channel: 'calls',
        title: 'Call centre sheet',
        description: 'Phones + last outcome',
        note: '<b>Do-not-call respected:</b> everyone on the do-not-contact list is left out of every export.',
    },
];

const form = useForm({
    channel: props.channel,
    hashed: props.hashByDefault,
    purpose: '',
    filters: {} as Record<string, string>,
});

const format = computed(
    () => FORMATS.find((f) => f.channel === form.channel) ?? FORMATS[0],
);
const rowCount = computed(() => props.channelCounts?.[form.channel] ?? null);
const numberFormat = (value: number): string => value.toLocaleString('en-US');

watch(open, (isOpen) => {
    if (isOpen) {
        form.channel = props.channel;
        form.hashed = props.hashByDefault;
        form.clearErrors();
        exportStatus.value = null;
        void loadPreview();
    } else {
        stopPolling();
    }
});

/* ---------- preview ---------- */
const preview = ref('');
const previewLoading = ref(false);

const csvCell = (value: string): string =>
    /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;

async function loadPreview(): Promise<void> {
    previewLoading.value = true;

    const params: Record<string, string> = {
        channel: form.channel,
        hashed: form.hashed ? '1' : '0',
    };
    for (const [key, value] of Object.entries(props.filters)) {
        params[`filter[${key}]`] = value;
    }

    try {
        const { data } = await http.get<{
            data: { columns: string[]; rows: string[][]; total: number };
        }>(exportPreview().url, { params });
        const { columns, rows, total } = data.data;
        const lines = [columns, ...rows].map((row) =>
            row.map(csvCell).join(','),
        );
        if (total > rows.length) {
            lines.push(
                `… ${numberFormat(total - rows.length)} more rows in the full export`,
            );
        }
        preview.value = lines.join('\n');
    } catch {
        preview.value = '';
    } finally {
        previewLoading.value = false;
    }
}

const loadPreviewDebounced = useDebounceFn(loadPreview, 150);
watch(
    () => [form.channel, form.hashed],
    () => open.value && void loadPreviewDebounced(),
);

/* ---------- start + progress ---------- */
const exportStatus = ref<ExportRow | null>(null);
let pollTimer: ReturnType<typeof setInterval> | undefined;

function stopPolling(): void {
    clearInterval(pollTimer);
    pollTimer = undefined;
}

async function poll(id: number): Promise<void> {
    try {
        const { data } = await http.get<{ data: ExportRow }>(
            exportShow(id).url,
            { silent: true },
        );
        exportStatus.value = data.data;
        if (data.data.is_finished) {
            stopPolling();
        }
    } catch {
        stopPolling();
    }
}

function startPolling(id: number): void {
    stopPolling();
    void poll(id);
    pollTimer = setInterval(() => void poll(id), 1500);
}

onBeforeUnmount(stopPolling);

function submit(): void {
    form.filters = props.filters;
    form.post(storeExport().url, {
        preserveScroll: true,
        preserveState: true,
        onFlash: (flash) => {
            const id = (flash as { export_id?: number }).export_id;
            if (id) {
                form.reset('purpose');
                startPolling(id);
            }
        },
    });
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
                            Export audience
                        </DialogTitle>
                        <DialogDescription
                            class="m-0 text-[12.5px] text-subtle"
                        >
                            <template v-if="rowCount !== null"
                                >{{ numberFormat(rowCount) }} contacts ·
                            </template>
                            {{ format.title }}
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                    >
                        Close
                    </DialogClose>
                </header>

                <div class="flex flex-col gap-3.5 px-[18px] py-4">
                    <fieldset
                        class="m-0 grid grid-cols-3 gap-2 border-0 p-0 max-[560px]:grid-cols-1"
                        :disabled="exportStatus !== null"
                    >
                        <legend class="sr-only">Format</legend>
                        <label
                            v-for="option in FORMATS"
                            :key="option.channel"
                            class="flex cursor-pointer items-start gap-2 rounded-[9px] border px-2.5 py-[9px] text-[12.5px]"
                            :class="
                                form.channel === option.channel
                                    ? 'border-ink bg-ink-soft'
                                    : 'border-line'
                            "
                        >
                            <input
                                v-model="form.channel"
                                type="radio"
                                name="format"
                                class="mt-[3px] accent-ink"
                                :value="option.channel"
                            />
                            <span
                                >{{ option.title
                                }}<small class="block text-[11px] text-subtle"
                                    >{{ option.description }}
                                    <template v-if="channelCounts"
                                        >·
                                        {{
                                            numberFormat(
                                                channelCounts[option.channel],
                                            )
                                        }}
                                        rows</template
                                    ></small
                                ></span
                            >
                        </label>
                    </fieldset>

                    <Toggle
                        v-if="format.hashable"
                        v-model="form.hashed"
                        :disabled="exportStatus !== null"
                        label="Hash identifiers with SHA-256 (recommended for ad platforms)"
                    />

                    <!-- eslint-disable-next-line vue/no-v-html -- static copy defined above -->
                    <div
                        class="rounded-[9px] bg-sand-soft px-3 py-2.5 text-[12.5px] text-fg [&_b]:text-sand"
                        v-html="format.note"
                    ></div>

                    <textarea
                        :value="preview"
                        readonly
                        aria-label="Export preview"
                        class="min-h-[180px] w-full resize-y rounded-lg border border-line bg-surface-2 p-2.5 font-mono text-[11.5px] leading-normal text-fg"
                        :class="{ 'opacity-60': previewLoading }"
                        dir="ltr"
                    ></textarea>

                    <template v-if="exportStatus === null">
                        <FormField
                            v-slot="field"
                            label="Purpose"
                            required
                            hint="Which campaign or task is this for? It is saved in the export log."
                            :error="form.errors.purpose"
                        >
                            <textarea
                                :id="field.id"
                                v-model="form.purpose"
                                rows="2"
                                maxlength="500"
                                required
                                aria-required="true"
                                dir="auto"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.describedBy"
                                :class="fieldClasses"
                            ></textarea>
                        </FormField>
                        <p
                            v-if="form.errors.channel || form.errors.filters"
                            class="m-0 text-xs text-bad"
                        >
                            {{ form.errors.channel ?? form.errors.filters }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                                :disabled="form.processing || rowCount === 0"
                                @click="submit"
                            >
                                Start export
                            </button>
                            <span class="text-xs text-subtle"
                                >Every export is logged with your name and
                                purpose. Do-not-contact is always left
                                out.</span
                            >
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex flex-wrap items-center gap-2.5 rounded-[9px] border border-line px-3 py-2.5 text-[13px]"
                        role="status"
                    >
                        <template v-if="exportStatus.status.key === 'done'">
                            <span
                                >Export ready:
                                <b class="num">{{
                                    numberFormat(exportStatus.row_count ?? 0)
                                }}</b>
                                rows.</span
                            >
                            <a
                                v-if="exportStatus.download_url"
                                :href="exportStatus.download_url"
                                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg no-underline"
                                >Download CSV</a
                            >
                        </template>
                        <span
                            v-else-if="exportStatus.status.key === 'failed'"
                            class="text-bad"
                            >The export failed. Try again, or ask an admin to
                            check the queue.</span
                        >
                        <span v-else class="text-subtle"
                            >{{ exportStatus.status.label }}… The file is being
                            written in the background; you can close this
                            dialog.</span
                        >
                        <Link
                            v-if="can('exports.view_log')"
                            :href="exportsIndex()"
                            class="text-[12.5px] font-medium text-ink"
                            >Open the export log</Link
                        >
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
