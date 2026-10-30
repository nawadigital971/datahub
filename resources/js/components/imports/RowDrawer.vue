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
import { computed } from 'vue';
import type { StagedRow } from '@/types';

const props = defineProps<{ row: StagedRow | null }>();
const open = defineModel<boolean>('open', { default: false });

const parsed = computed(() => {
    const row = props.row;
    if (!row) {
        return [];
    }

    return [
        ['Type', row.type === 2 ? 'Business' : 'Person'],
        ['English name', row.name_en],
        ['Arabic name', row.name_ar],
        ['Company', row.company_name],
        [
            'Gender',
            row.gender === 'm' ? 'Male' : row.gender === 'f' ? 'Female' : null,
        ],
        ['Mobile', row.mobile_e164],
        ['Landline', row.landline_e164],
        ['Email', row.email],
        ['Emirate', row.region?.label ?? null],
        ['Area', row.area?.label ?? null],
        ['Sector', row.sector?.label ?? null],
        ['Nationality', row.nationality?.label ?? null],
        ['Address', row.address_raw],
        ['Year', row.seen_year === null ? null : String(row.seen_year)],
        ['Last call', row.call_outcome],
    ] as [string, string | null][];
});

const extra = computed(() =>
    Object.entries(props.row?.extra ?? {}).map(([key, value]) => [
        key.replace(/_/g, ' '),
        Array.isArray(value) ? value.join(', ') : String(value),
    ]),
);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-20 bg-[rgba(10,20,17,.25)]" />
            <DialogContent
                class="fixed inset-y-0 right-0 z-20 flex w-[min(520px,100%)] flex-col overflow-auto border-l border-line bg-surface shadow-[0_0_40px_rgba(0,0,0,.2)]"
            >
                <header
                    class="flex items-start justify-between gap-2.5 border-b border-line px-[18px] py-4"
                >
                    <div>
                        <DialogTitle
                            class="m-0 font-display text-lg font-semibold"
                        >
                            Row {{ row?.row_number }}
                        </DialogTitle>
                        <DialogDescription
                            class="m-0 text-[12.5px] text-subtle"
                        >
                            The original cells, never changed, and what the
                            import made of them.
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-lg border border-line bg-transparent px-3.5 py-2 text-[13px] font-semibold text-fg"
                        >Close</DialogClose
                    >
                </header>
                <div
                    v-if="row"
                    class="flex flex-col gap-4 px-[18px] py-4 text-[12.5px]"
                >
                    <section>
                        <h3
                            class="m-0 mb-1.5 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
                        >
                            Problems
                        </h3>
                        <p
                            v-if="row.issues.length === 0"
                            class="m-0 text-subtle"
                        >
                            None.
                        </p>
                        <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                            <li
                                v-for="(issue, index) in row.issues"
                                :key="index"
                                class="rounded-lg px-2.5 py-1.5"
                                :class="
                                    issue.severity === 2
                                        ? 'bg-bad-soft'
                                        : 'bg-sand-soft'
                                "
                            >
                                {{ issue.message }}
                                <span
                                    v-if="issue.suggestion"
                                    class="block font-mono text-xs text-subtle"
                                    >Suggestion: {{ issue.suggestion }}</span
                                >
                            </li>
                        </ul>
                    </section>
                    <section
                        class="grid grid-cols-2 gap-4 max-[560px]:grid-cols-1"
                    >
                        <div>
                            <h3
                                class="m-0 mb-1.5 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
                            >
                                Original row
                            </h3>
                            <dl class="m-0">
                                <div
                                    v-for="(value, key) in row.raw"
                                    :key="key"
                                    class="border-b border-line py-1"
                                >
                                    <dt
                                        class="text-[11px] text-subtle"
                                        dir="auto"
                                    >
                                        {{ key }}
                                    </dt>
                                    <dd class="m-0 break-words" dir="auto">
                                        {{ value }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3
                                class="m-0 mb-1.5 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
                            >
                                Parsed
                            </h3>
                            <dl class="m-0">
                                <div
                                    v-for="[label, value] in parsed"
                                    :key="label"
                                    class="border-b border-line py-1"
                                >
                                    <dt class="text-[11px] text-subtle">
                                        {{ label }}
                                    </dt>
                                    <dd class="m-0 break-words" dir="auto">
                                        <template v-if="value">{{
                                            value
                                        }}</template>
                                        <span v-else class="text-faint">—</span>
                                    </dd>
                                </div>
                                <div
                                    v-for="[label, value] in extra"
                                    :key="label"
                                    class="border-b border-line py-1"
                                >
                                    <dt
                                        class="text-[11px] text-subtle capitalize"
                                    >
                                        {{ label }}
                                    </dt>
                                    <dd class="m-0 break-words" dir="auto">
                                        {{ value }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
