<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import BatchStatusPill from '@/components/imports/BatchStatusPill.vue';
import {
    discard as discardBatch,
    index as importsIndex,
} from '@/routes/imports';
import { update as updateMapping } from '@/routes/imports/mapping';
import { fieldClasses } from '@/lib/fieldClasses';
import type { ImportBatch, SelectOption } from '@/types';

const props = defineProps<{
    batch: ImportBatch;
    headers: string[];
    columnMap: Record<string, string>;
    templateId: number | null;
    samples: string[][];
    templates: (SelectOption<number> & {
        column_map: Record<string, string>;
    })[];
    targets: SelectOption<string>[];
}>();

const form = useForm({
    column_map: { ...props.columnMap } as Record<string, string>,
    mapping_template_id: props.templateId,
    save_as_template: '',
});

const normalize = (header: string): string =>
    header.trim().toLowerCase().replace(/\s+/g, ' ');

/** Targets plus any custom "extra.<key>" value already in the mapping. */
const targetOptions = computed<SelectOption<string>[]>(() => {
    const custom = Object.values(form.column_map)
        .filter((target) => target.startsWith('extra.'))
        .filter((target, index, all) => all.indexOf(target) === index)
        .map((target) => ({
            value: target,
            label: `Keep as extra: ${target.slice(6).replace(/_/g, ' ')}`,
        }));

    return [...props.targets, ...custom];
});

const template = computed({
    get: () => form.mapping_template_id,
    set: (id: number | null) => {
        form.mapping_template_id = id;
        const chosen = props.templates.find((t) => t.value === id);
        if (!chosen) {
            return;
        }
        const byHeader = new Map(
            Object.entries(chosen.column_map).map(([header, target]) => [
                normalize(header),
                target,
            ]),
        );
        for (const header of props.headers) {
            const target = byHeader.get(normalize(header));
            if (target) {
                form.column_map[header] = target;
            }
        }
    },
});

const samplesFor = (index: number): string[] =>
    props.samples
        .map((row) => row[index] ?? '')
        .filter((value) => value !== '')
        .slice(0, 3);

function submit(): void {
    form.put(updateMapping(props.batch.id).url);
}

function discard(): void {
    router.post(discardBatch(props.batch.id).url);
}
</script>

<template>
    <Head title="Map columns" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">
            Map columns ·
            <bdi>{{ batch.filename }}</bdi>
        </h2>
        <p class="m-0 flex items-center gap-2 text-[12.5px] text-subtle">
            {{ batch.source }}
            <BatchStatusPill :status="batch.status" />
        </p>
    </div>
    <p class="m-0 mb-4 max-w-[80ch] text-subtle">
        <template v-if="templateId"
            >This file looks like the
            <b class="text-fg">{{
                templates.find((t) => t.value === templateId)?.label
            }}</b>
            layout, so the columns are already mapped. Check them, then analyse
            the file.</template
        >
        <template v-else
            >This layout is new. Choose where each column goes; anything you
            keep as extra stays on the contact, and the original row is always
            saved.</template
        >
    </p>

    <form
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
        @submit.prevent="submit"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <div class="flex items-center gap-2.5 text-[13px]">
                <span class="font-medium">Layout</span>
                <div class="w-64">
                    <AppSelect
                        v-model="template"
                        :options="templates"
                        :searchable="false"
                        placeholder="Custom mapping"
                        size="sm"
                        aria-label="Mapping template"
                    />
                </div>
            </div>
            <span class="text-[12.5px] text-subtle num"
                >{{ headers.length }} columns</span
            >
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'Column in the file',
                                'Sample values',
                                'Goes to',
                            ]"
                            :key="heading"
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ heading }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(header, index) in headers" :key="header">
                        <td
                            class="border-t border-line px-3 py-2 align-top font-medium"
                            dir="auto"
                        >
                            {{ header }}
                        </td>
                        <td
                            class="max-w-[360px] border-t border-line px-3 py-2 align-top"
                        >
                            <div
                                v-for="(sample, sampleIndex) in samplesFor(
                                    index,
                                )"
                                :key="sampleIndex"
                                class="truncate font-mono text-xs text-subtle"
                                dir="auto"
                            >
                                {{ sample }}
                            </div>
                            <span
                                v-if="samplesFor(index).length === 0"
                                class="text-faint"
                                >empty</span
                            >
                        </td>
                        <td
                            class="w-72 border-t border-line px-3 py-2 align-top"
                        >
                            <AppSelect
                                v-model="form.column_map[header]"
                                :options="targetOptions"
                                size="sm"
                                :aria-label="`Destination of ${header}`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="max-w-[420px] border-t border-line px-4 py-3">
            <FormField
                v-slot="field"
                label="Save these columns as a template"
                hint="Files with the same headers will then be mapped automatically. Use an existing name to update that template."
                :error="form.errors.save_as_template"
            >
                <input
                    :id="field.id"
                    v-model="form.save_as_template"
                    maxlength="100"
                    placeholder="e.g. Expo visitor list"
                    dir="auto"
                    :class="fieldClasses"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
        </div>
        <div
            class="flex flex-wrap items-center gap-2.5 border-t border-line px-4 py-3"
        >
            <button
                type="submit"
                class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                :disabled="form.processing"
            >
                Analyse file
            </button>
            <Link
                :href="importsIndex()"
                class="rounded-lg border border-line px-3.5 py-2 text-[13px] font-semibold text-fg no-underline"
                >Back</Link
            >
            <button
                v-if="batch.can.discard"
                type="button"
                class="ml-auto border-0 bg-transparent px-1 py-0.5 text-[12.5px] font-medium text-bad"
                @click="discard"
            >
                Discard this import
            </button>
            <p
                v-if="form.errors.column_map"
                class="m-0 w-full text-xs text-bad"
            >
                {{ form.errors.column_map }}
            </p>
        </div>
    </form>
</template>
