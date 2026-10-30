<script setup lang="ts">
import type { ChannelKey } from '@/types';

defineProps<{
    counts: Record<ChannelKey, number> | null;
    canExport: boolean;
}>();

defineEmits<{
    export: [channel: ChannelKey];
}>();

const CHANNELS: {
    key: ChannelKey;
    name: string;
    short: string;
    glyph: string;
    rule: string;
    note: string;
}[] = [
    {
        key: 'email',
        name: 'Email campaign',
        short: 'Email',
        glyph: '@',
        rule: 'Valid email, not on do-not-contact',
        note: 'Old lists: warm up and verify before a big send',
    },
    {
        key: 'whatsapp',
        name: 'WhatsApp',
        short: 'WhatsApp',
        glyph: 'WA',
        rule: 'Any mobile in international format',
        note: 'Business API needs opt-in',
    },
    {
        key: 'sms',
        name: 'SMS',
        short: 'SMS',
        glyph: 'SMS',
        rule: 'UAE mobile (9715…) only',
        note: 'Sender ID must be registered',
    },
    {
        key: 'meta',
        name: 'Meta audience',
        short: 'Meta',
        glyph: 'f',
        rule: 'Phone or email, hashed SHA-256 on export',
        note: '',
    },
    {
        key: 'google',
        name: 'Google Customer Match',
        short: 'Google',
        glyph: 'G',
        rule: 'Email or phone, hashed',
        note: 'Match rates drop on old data',
    },
    {
        key: 'calls',
        name: 'Call centre',
        short: 'Call',
        glyph: '☎',
        rule: 'Any phone, excludes do-not-call',
        note: '',
    },
];

const format = (value: number): string => value.toLocaleString('en-US');
</script>

<template>
    <div
        class="grid grid-cols-6 gap-2.5 max-[1200px]:grid-cols-3 max-[560px]:grid-cols-2"
    >
        <div
            v-for="channel in CHANNELS"
            :key="channel.key"
            class="flex min-w-0 flex-col gap-1.5 rounded-card border border-line bg-surface p-3"
        >
            <div class="flex items-center gap-[7px] text-[13px] font-semibold">
                <span
                    class="grid size-[22px] flex-none place-items-center rounded-md bg-surface-2 font-mono text-[11px] font-medium text-ink"
                    aria-hidden="true"
                    >{{ channel.glyph }}</span
                >
                {{ channel.name }}
            </div>
            <div
                v-if="counts"
                class="font-display text-2xl leading-[1.1] font-semibold num"
            >
                {{ format(counts[channel.key]) }}
            </div>
            <div
                v-else
                class="h-[26px] w-20 animate-pulse rounded bg-surface-2"
            ></div>
            <div class="min-h-[34px] text-[11.5px] text-subtle">
                {{ channel.rule }}
            </div>
            <div v-if="channel.note" class="text-[11px] font-medium text-warn">
                {{ channel.note }}
            </div>
            <button
                v-if="canExport"
                type="button"
                class="mt-auto rounded-[7px] border border-ink bg-transparent px-2 py-1.5 text-[12.5px] font-semibold text-ink enabled:hover:bg-ink enabled:hover:text-ink-fg disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!counts || counts[channel.key] === 0"
                @click="$emit('export', channel.key)"
            >
                Export for {{ channel.short }}
            </button>
        </div>
    </div>
</template>
