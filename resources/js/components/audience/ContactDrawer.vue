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
import { http } from '@/lib/http';
import { show as showContact } from '@/routes/contacts';
import type { ContactDetails } from '@/types';

const props = defineProps<{ contactId: number | null }>();
const open = defineModel<boolean>('open', { default: false });

const contact = ref<ContactDetails | null>(null);
const loading = ref(false);
const failed = ref(false);
let request = 0;

async function load(id: number): Promise<void> {
    const current = ++request;
    loading.value = true;
    failed.value = false;
    contact.value = null;
    try {
        const { data } = await http.get<ContactDetails>(showContact(id).url);
        if (current === request) {
            contact.value = data;
        }
    } catch {
        if (current === request) {
            failed.value = true;
        }
    } finally {
        if (current === request) {
            loading.value = false;
        }
    }
}

watch(
    () => [open.value, props.contactId] as const,
    ([isOpen, id]) => {
        if (isOpen && id !== null) {
            void load(id);
        }
    },
    { immediate: true },
);

const title = computed(() => {
    const c = contact.value;

    return c ? (c.company_name ?? c.name_en ?? c.name_ar ?? '(no name)') : '';
});

const flags = computed(() => {
    const status = contact.value?.status;
    if (!status) {
        return [];
    }

    return [
        status.do_not_contact && {
            label: `Do not contact${status.dnc_reason ? ` · ${status.dnc_reason}` : ''}`,
            tone: 'bg-bad-soft text-bad',
        },
        status.is_junk && { label: 'Junk name', tone: 'bg-bad-soft text-bad' },
        status.check_spelling && {
            label: 'Check spelling',
            tone: 'bg-surface-2 text-subtle',
        },
    ].filter((flag): flag is { label: string; tone: string } => Boolean(flag));
});

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '';

const HEADING =
    'm-0 mb-1.5 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase';
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-20 bg-[rgba(10,20,17,.25)]" />
            <DialogContent
                class="fixed inset-y-0 right-0 z-20 flex w-[min(560px,100%)] flex-col overflow-auto border-l border-line bg-surface shadow-[0_0_40px_rgba(0,0,0,.2)]"
            >
                <header
                    class="flex items-start justify-between gap-2.5 border-b border-line px-[18px] py-4"
                >
                    <div class="min-w-0">
                        <DialogTitle
                            class="m-0 font-display text-lg font-semibold break-words"
                            dir="auto"
                        >
                            <template v-if="contact">{{ title }}</template>
                            <span
                                v-else
                                class="inline-block h-5 w-40 animate-pulse rounded bg-surface-2"
                            ></span>
                        </DialogTitle>
                        <DialogDescription
                            class="m-0 text-[12.5px] text-subtle"
                        >
                            Everything stored about this contact
                            <template v-if="contact"
                                >· #<bdi>{{ contact.id }}</bdi></template
                            >
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                        >Close</DialogClose
                    >
                </header>

                <div
                    v-if="loading"
                    class="flex flex-col gap-3 px-[18px] py-4"
                    aria-busy="true"
                >
                    <span
                        v-for="n in 6"
                        :key="n"
                        class="h-10 animate-pulse rounded-lg bg-surface-2"
                    ></span>
                </div>

                <p
                    v-else-if="failed"
                    class="m-0 px-[18px] py-6 text-[12.5px] text-subtle"
                >
                    This contact could not be loaded. Close the panel and try
                    again.
                </p>

                <div
                    v-else-if="contact"
                    class="flex flex-col gap-5 px-[18px] py-4 text-[12.5px]"
                >
                    <div v-if="flags.length" class="flex flex-wrap gap-1">
                        <span
                            v-for="flag in flags"
                            :key="flag.label"
                            class="inline-block rounded-full px-[7px] py-px text-[11px] font-medium"
                            :class="flag.tone"
                            >{{ flag.label }}</span
                        >
                    </div>

                    <section>
                        <h3 :class="HEADING">Names</h3>
                        <dl class="m-0">
                            <div
                                v-if="contact.company_name"
                                class="border-b border-line py-1"
                            >
                                <dt class="text-[11px] text-subtle">Company</dt>
                                <dd class="m-0 break-words" dir="auto">
                                    {{ contact.company_name }}
                                </dd>
                            </div>
                            <div class="border-b border-line py-1">
                                <dt class="text-[11px] text-subtle">
                                    English name
                                </dt>
                                <dd class="m-0 break-words" dir="auto">
                                    <template v-if="contact.name_en">{{
                                        contact.name_en
                                    }}</template>
                                    <span v-else class="text-faint">—</span>
                                </dd>
                            </div>
                            <div class="border-b border-line py-1">
                                <dt class="text-[11px] text-subtle">
                                    Arabic name
                                </dt>
                                <dd class="m-0 break-words" dir="rtl" lang="ar">
                                    <template v-if="contact.name_ar">{{
                                        contact.name_ar
                                    }}</template>
                                    <span v-else class="text-faint">—</span>
                                </dd>
                            </div>
                            <div class="border-b border-line py-1">
                                <dt class="text-[11px] text-subtle">
                                    Also known as
                                </dt>
                                <dd class="m-0">
                                    <ul
                                        v-if="contact.other_names.length"
                                        class="m-0 flex list-none flex-wrap gap-1 p-0 pt-0.5"
                                    >
                                        <li
                                            v-for="name in contact.other_names"
                                            :key="name"
                                            class="rounded-full bg-surface-2 px-2 py-px"
                                            dir="auto"
                                        >
                                            {{ name }}
                                        </li>
                                    </ul>
                                    <span v-else class="text-faint"
                                        >No other names</span
                                    >
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section>
                        <h3 :class="HEADING">Phones and emails</h3>
                        <p
                            v-if="contact.masked"
                            class="m-0 mb-1.5 text-xs text-subtle"
                        >
                            Hidden in part: your role can't see full phone
                            numbers and emails.
                        </p>
                        <ul class="m-0 flex list-none flex-col p-0">
                            <li
                                v-for="phone in contact.phones"
                                :key="phone.value"
                                class="flex items-center justify-between gap-2 border-b border-line py-1"
                            >
                                <span class="font-mono num" dir="ltr">{{
                                    phone.value
                                }}</span>
                                <span class="text-xs text-subtle"
                                    >{{ phone.type
                                    }}<template v-if="phone.is_primary">
                                        · main</template
                                    ></span
                                >
                            </li>
                            <li
                                v-for="email in contact.emails"
                                :key="email.value"
                                class="flex items-center justify-between gap-2 border-b border-line py-1"
                            >
                                <span class="font-mono break-all" dir="ltr">{{
                                    email.value
                                }}</span>
                                <span
                                    class="text-xs whitespace-nowrap text-subtle"
                                    >Email<template v-if="email.is_primary">
                                        · main</template
                                    ><template v-if="email.status">
                                        · {{ email.status }}</template
                                    ></span
                                >
                            </li>
                            <li
                                v-if="
                                    !contact.phones.length &&
                                    !contact.emails.length
                                "
                                class="py-1 text-faint"
                            >
                                No phones or emails.
                            </li>
                        </ul>
                    </section>

                    <section
                        v-if="contact.details.length || contact.extra.length"
                    >
                        <h3 :class="HEADING">Details</h3>
                        <dl
                            class="m-0 grid grid-cols-2 gap-x-4 max-[560px]:grid-cols-1"
                        >
                            <div
                                v-for="row in [
                                    ...contact.details,
                                    ...contact.extra,
                                ]"
                                :key="row.label"
                                class="border-b border-line py-1"
                            >
                                <dt class="text-[11px] text-subtle capitalize">
                                    {{ row.label }}
                                </dt>
                                <dd class="m-0 break-words" dir="auto">
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section>
                        <h3 :class="HEADING">
                            Sources ({{ contact.sources.length }})
                        </h3>
                        <p
                            v-if="!contact.sources.length"
                            class="m-0 text-subtle"
                        >
                            No source recorded.
                        </p>
                        <details
                            v-for="(source, index) in contact.sources"
                            :key="index"
                            class="mb-1.5 rounded-lg border border-line"
                        >
                            <summary
                                class="flex cursor-pointer flex-wrap items-baseline justify-between gap-x-2 px-2.5 py-2"
                            >
                                <strong>{{ source.source }}</strong>
                                <span class="text-xs text-subtle num"
                                    ><template v-if="source.year"
                                        >{{ source.year }} · </template
                                    ><template v-if="source.file"
                                        ><bdi>{{ source.file }}</bdi></template
                                    ><template v-if="source.row_number">
                                        · row {{ source.row_number }}</template
                                    ><template v-if="source.added_at">
                                        · added
                                        {{
                                            formatDate(source.added_at)
                                        }}</template
                                    ></span
                                >
                            </summary>
                            <dl class="m-0 border-t border-line px-2.5 py-1.5">
                                <div
                                    v-for="cell in source.raw"
                                    :key="cell.column"
                                    class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-2 border-b border-line py-1 last:border-b-0"
                                >
                                    <dt
                                        class="text-[11px] break-words text-subtle"
                                        dir="auto"
                                    >
                                        {{ cell.column }}
                                    </dt>
                                    <dd class="m-0 break-words" dir="auto">
                                        <template v-if="cell.value">{{
                                            cell.value
                                        }}</template>
                                        <span v-else class="text-faint">—</span>
                                    </dd>
                                </div>
                            </dl>
                        </details>
                        <p
                            v-if="contact.sources.length"
                            class="m-0 text-xs text-subtle"
                        >
                            Open a source to see the original row exactly as it
                            was imported.
                        </p>
                    </section>

                    <section>
                        <h3 :class="HEADING">History</h3>
                        <p
                            v-if="!contact.history.length"
                            class="m-0 text-subtle"
                        >
                            No changes, merges or calls yet.
                        </p>
                        <ol class="m-0 flex list-none flex-col p-0">
                            <li
                                v-for="(event, index) in contact.history"
                                :key="index"
                                class="border-b border-line py-1.5"
                            >
                                <div
                                    class="flex flex-wrap items-baseline justify-between gap-x-2"
                                >
                                    <strong class="text-xs">{{
                                        event.type
                                    }}</strong>
                                    <span class="text-xs text-subtle num"
                                        >{{ formatDate(event.at)
                                        }}<template v-if="event.by">
                                            ·
                                            <bdi>{{ event.by }}</bdi></template
                                        ></span
                                    >
                                </div>
                                <div class="break-words" dir="auto">
                                    {{ event.text }}
                                </div>
                            </li>
                        </ol>
                        <p
                            v-if="contact.history_more"
                            class="m-0 mt-1.5 text-xs text-subtle"
                        >
                            Showing the latest 100 entries.
                        </p>
                    </section>

                    <p class="m-0 text-xs text-subtle">
                        Added {{ formatDate(contact.created_at) }} · last
                        changed {{ formatDate(contact.updated_at) }}
                    </p>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
