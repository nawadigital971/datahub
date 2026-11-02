<script setup lang="ts">
import type { ContactRow } from '@/types';

defineProps<{
    contacts: ContactRow[];
    total: number | null;
    position: number;
    perPage: number;
    hasPrevious: boolean;
    hasNext: boolean;
    canExport: boolean;
}>();

defineEmits<{
    previous: [];
    next: [];
    openFilters: [];
    export: [];
    show: [contactId: number];
}>();

const format = (value: number): string => value.toLocaleString('en-US');

const TAG_CLASSES = {
    flag: 'bg-bad-soft text-bad',
    neutral: 'bg-surface-2 text-subtle',
    ok: 'bg-ink-soft text-ink',
} as const;
</script>

<template>
    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    class="hidden rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg max-[900px]:inline-flex"
                    @click="$emit('openFilters')"
                >
                    Filters
                </button>
                <strong v-if="total !== null" class="num"
                    >{{ format(total) }} contacts</strong
                >
                <span
                    v-else
                    class="h-4 w-28 animate-pulse rounded bg-surface-2"
                ></span>
            </div>
            <div v-if="canExport" class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                    @click="$emit('export')"
                >
                    Export this audience
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'Contact',
                                'Type',
                                'Source',
                                'Location',
                                'Mobile',
                                'Email',
                                'Reach',
                                'Status',
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
                        v-for="contact in contacts"
                        :key="contact.id"
                        class="cursor-pointer hover:bg-surface-2/60"
                        @click="$emit('show', contact.id)"
                    >
                        <td class="border-t border-line px-3 py-2 align-top">
                            <button
                                type="button"
                                class="m-0 border-0 bg-transparent p-0 text-left font-semibold text-fg hover:underline"
                                dir="auto"
                                :aria-label="`Show details of ${contact.company_name ?? contact.name_en ?? contact.name_ar ?? 'this contact'}`"
                                @click.stop="$emit('show', contact.id)"
                            >
                                {{
                                    contact.company_name ??
                                    contact.name_en ??
                                    contact.name_ar ??
                                    '(no name)'
                                }}
                            </button>
                            <template v-if="contact.company_name">
                                <div
                                    v-if="contact.name_en || contact.name_ar"
                                    class="text-xs text-subtle"
                                    dir="auto"
                                >
                                    Contact:
                                    <bdi>{{
                                        contact.name_en ?? contact.name_ar
                                    }}</bdi>
                                </div>
                            </template>
                            <template v-else>
                                <div
                                    v-if="contact.name_en && contact.name_ar"
                                    class="text-xs text-subtle"
                                    dir="rtl"
                                    lang="ar"
                                >
                                    {{ contact.name_ar }}
                                </div>
                            </template>
                            <div
                                v-if="contact.other_names.length"
                                class="text-xs text-subtle"
                                :title="contact.other_names.join(' · ')"
                            >
                                Also:
                                <template
                                    v-for="(
                                        name, index
                                    ) in contact.other_names.slice(0, 2)"
                                    :key="name"
                                    ><template v-if="index > 0">, </template
                                    ><bdi dir="auto">{{ name }}</bdi></template
                                ><template
                                    v-if="contact.other_names.length > 2"
                                >
                                    +{{
                                        contact.other_names.length - 2
                                    }}
                                    more</template
                                >
                            </div>
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            <span
                                class="inline-block rounded-full px-[7px] py-px text-[11px] font-medium whitespace-nowrap"
                                :class="
                                    contact.is_business
                                        ? 'bg-sand-soft text-sand'
                                        : 'bg-surface-2 text-subtle'
                                "
                                >{{
                                    contact.is_business
                                        ? (contact.sector ?? 'Business')
                                        : 'Person'
                                }}</span
                            >
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top whitespace-nowrap"
                        >
                            {{ contact.source.label ?? '—' }}
                            <span v-if="contact.source.more" class="text-subtle"
                                >+{{ contact.source.more }}</span
                            >
                            <div class="text-xs text-subtle num">
                                {{ contact.source.year ?? '' }}
                            </div>
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top whitespace-nowrap"
                        >
                            <template v-if="contact.location.region">
                                {{ contact.location.region }}
                                <div class="text-xs text-subtle" dir="auto">
                                    {{ contact.location.area ?? '' }}
                                </div>
                            </template>
                            <template v-else>{{
                                contact.location.country ?? '—'
                            }}</template>
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top font-mono whitespace-nowrap num"
                            dir="ltr"
                        >
                            <template v-if="contact.mobile">{{
                                contact.mobile
                            }}</template>
                            <span v-else class="text-faint">—</span>
                            <div
                                v-if="contact.landline"
                                class="text-xs text-subtle"
                            >
                                Tel {{ contact.landline }}
                            </div>
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 align-top font-mono"
                            dir="ltr"
                        >
                            <template v-if="contact.email">{{
                                contact.email
                            }}</template>
                            <span v-else class="text-faint">—</span>
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            <div class="flex gap-1">
                                <span
                                    v-for="dot in [
                                        {
                                            key: 'email',
                                            glyph: '@',
                                            title: 'Email',
                                            on: contact.reach.email,
                                        },
                                        {
                                            key: 'whatsapp',
                                            glyph: 'W',
                                            title: 'WhatsApp',
                                            on: contact.reach.whatsapp,
                                        },
                                        {
                                            key: 'sms',
                                            glyph: 'S',
                                            title: 'SMS',
                                            on: contact.reach.sms,
                                        },
                                    ]"
                                    :key="dot.key"
                                    class="grid size-5 place-items-center rounded-[5px] font-mono text-[10px]"
                                    :class="
                                        dot.on
                                            ? 'bg-ink-soft font-semibold text-ink'
                                            : 'bg-surface-2 text-faint'
                                    "
                                    :title="dot.title"
                                    >{{ dot.glyph }}</span
                                >
                            </div>
                        </td>
                        <td class="border-t border-line px-3 py-2 align-top">
                            <span class="inline-flex flex-wrap gap-1">
                                <span
                                    v-for="flag in contact.flags"
                                    :key="flag.label"
                                    class="inline-block rounded-full px-[7px] py-px text-[11px] font-medium whitespace-nowrap"
                                    :class="TAG_CLASSES[flag.tone]"
                                    >{{ flag.label }}</span
                                >
                            </span>
                        </td>
                    </tr>
                    <tr v-if="contacts.length === 0">
                        <td
                            colspan="8"
                            class="border-t border-line p-[30px] text-center text-subtle"
                        >
                            No contacts match these filters. Remove a filter
                            above to widen the audience.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
        >
            <span v-if="contacts.length" class="num"
                >{{ format((position - 1) * perPage + 1) }}–{{
                    format((position - 1) * perPage + contacts.length)
                }}<template v-if="total !== null">
                    of {{ format(total) }}</template
                ></span
            >
            <button
                type="button"
                class="rounded-md border border-line bg-surface px-2.5 py-1 disabled:opacity-40"
                :disabled="!hasPrevious"
                @click="$emit('previous')"
            >
                Previous
            </button>
            <button
                type="button"
                class="rounded-md border border-line bg-surface px-2.5 py-1 disabled:opacity-40"
                :disabled="!hasNext"
                @click="$emit('next')"
            >
                Next
            </button>
        </div>
    </div>
</template>
