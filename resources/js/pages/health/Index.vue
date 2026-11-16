<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AppDialog from "@/components/AppDialog.vue";
import FormField from "@/components/FormField.vue";
import { fieldClasses } from "@/lib/fieldClasses";
import { index as audienceIndex } from "@/routes/audience";
import { store as storeEmailVerification } from "@/routes/health/email-verifications";

type Issue = {
    key: string;
    severity: "high" | "med" | "low";
    title: string;
    description: string;
    rule: string;
    count: number;
    audience: Record<string, string> | null;
};

type VerificationTask = {
    id: number;
    reoon_task_id: string | null;
    status: "submitted" | "completed" | "failed";
    status_label: string;
    count: number;
    summary: Record<string, number> | null;
    error: string | null;
    created_at: string | null;
    completed_at: string | null;
};

type EmailVerification = {
    configured: boolean;
    daily_limit: number;
    balance: { daily: number; instant: number } | null;
    balance_error: string | null;
    tasks: VerificationTask[];
};

const props = defineProps<{
    health?: { total: number; issues: Issue[] };
    /** Undefined while loading; null when the user may not run verification. */
    emailVerification?: EmailVerification | null;
}>();

const MAX_PER_TASK = 50000;
const TASK_TONE = {
    submitted: "bg-sand-soft text-sand",
    completed: "bg-ink-soft text-ink",
    failed: "bg-bad-soft text-bad",
} as const;

const dialogOpen = ref(false);
const form = useForm({ count: 0 as number | null });
const dailyCredits = computed(
    () => props.emailVerification?.balance?.daily ?? 0,
);
const fromInstant = computed(() =>
    Math.max(0, (form.count ?? 0) - dailyCredits.value),
);

function openVerify(): void {
    form.clearErrors();
    form.count =
        dailyCredits.value > 0
            ? dailyCredits.value
            : (props.emailVerification?.daily_limit ?? 1000);
    dialogOpen.value = true;
}

function verify(): void {
    form.post(storeEmailVerification().url, {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}

const summaryText = (summary: Record<string, number> | null): string =>
    summary
        ? Object.entries(summary)
              .map(([result, n]) => `${result.replace("_", " ")} ${format(n)}`)
              .join(" · ")
        : "";
const dateTime = new Intl.DateTimeFormat("en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
});
const formatDateTime = (value: string | null): string =>
    value ? dateTime.format(new Date(value)) : "—";

const STRIPE = { high: "bg-bad", med: "bg-warn", low: "bg-good" } as const;
const format = (value: number): string => value.toLocaleString("en-US");
const share = (count: number, total: number): string =>
    total > 0
        ? `${((count / total) * 100).toFixed(count / total < 0.01 ? 2 : 1)}%`
        : "0%";
</script>

<template>
    <Head title="Data health" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Data health</h2>
        <p v-if="health" class="m-0 text-[12.5px] text-subtle num">
            Across {{ format(health.total) }} contacts · updated after every
            import
        </p>
    </div>
    <p class="m-0 mb-4 max-w-[80ch] text-subtle">
        Live counts of the problems the import rules look for. New files are
        checked row by row before they reach the contacts; these numbers show
        what is still in the data.
    </p>

    <section
        v-if="emailVerification !== null"
        class="mb-4 rounded-card border border-line bg-surface px-4 py-3.5"
        aria-labelledby="email-verification-heading"
    >
        <div
            v-if="emailVerification === undefined"
            class="h-24 animate-pulse rounded-lg bg-surface-2"
            aria-hidden="true"
        ></div>
        <template v-else>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3
                        id="email-verification-heading"
                        class="m-0 text-sm font-semibold"
                    >
                        Email verification
                    </h3>
                    <p class="m-0 mt-1 max-w-[70ch] text-[12.5px] text-subtle">
                        Every day at 08:00 (Dubai) up to
                        <span class="num">{{
                            format(emailVerification.daily_limit)
                        }}</span>
                        unchecked emails are sent to the verifier. Bad addresses
                        are marked invalid and drop out of email reach.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="!emailVerification.configured"
                    @click="openVerify"
                >
                    Verify emails
                </button>
            </div>

            <p
                v-if="!emailVerification.configured"
                class="m-0 mt-3 text-[12.5px] text-sand"
            >
                Not set up yet: add <code>REOON_API_KEY</code> to the .env file.
            </p>
            <p
                v-else-if="emailVerification.balance_error"
                class="m-0 mt-3 text-[12.5px] text-bad"
            >
                {{ emailVerification.balance_error }}
            </p>
            <dl
                v-else-if="emailVerification.balance"
                class="m-0 mt-3 flex flex-wrap gap-x-8 gap-y-2"
            >
                <div>
                    <dt
                        class="text-[11px] tracking-[.06em] text-subtle uppercase"
                    >
                        Daily credits left
                    </dt>
                    <dd class="m-0 font-display text-[22px] font-semibold num">
                        {{ format(emailVerification.balance.daily) }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-[11px] tracking-[.06em] text-subtle uppercase"
                    >
                        Instant credits
                    </dt>
                    <dd class="m-0 font-display text-[22px] font-semibold num">
                        {{ format(emailVerification.balance.instant) }}
                    </dd>
                </div>
            </dl>

            <ul
                v-if="emailVerification.tasks.length > 0"
                class="m-0 mt-3 flex list-none flex-col gap-1.5 border-t border-line p-0 pt-3"
            >
                <li
                    v-for="task in emailVerification.tasks"
                    :key="task.id"
                    class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-[12.5px]"
                >
                    <span class="whitespace-nowrap text-subtle">{{
                        formatDateTime(task.created_at)
                    }}</span>
                    <span class="num">{{ format(task.count) }} emails</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="TASK_TONE[task.status]"
                        >{{ task.status_label }}</span
                    >
                    <span
                        v-if="task.status === 'completed'"
                        class="min-w-0 text-subtle num"
                        >{{ summaryText(task.summary) }}</span
                    >
                    <span v-else-if="task.error" class="min-w-0 text-bad">{{
                        task.error
                    }}</span>
                </li>
            </ul>
        </template>
    </section>

    <div v-if="health" class="grid grid-cols-2 gap-3.5 max-[900px]:grid-cols-1">
        <div
            v-for="issue in health.issues"
            :key="issue.key"
            class="grid grid-cols-[6px_minmax(0,1fr)] gap-3 overflow-hidden rounded-card border border-line bg-surface py-3.5 pr-4"
        >
            <div
                class="rounded-r"
                :class="STRIPE[issue.severity]"
                aria-hidden="true"
            ></div>
            <div>
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h3 class="m-0 text-sm font-semibold">{{ issue.title }}</h3>
                    <span class="font-display text-[22px] font-semibold num"
                        >{{ format(issue.count) }}
                        <span
                            v-if="issue.key !== 'waiting_imports'"
                            class="text-[13px] font-normal text-subtle"
                            >{{ share(issue.count, health.total) }}</span
                        ></span
                    >
                </div>
                <p class="m-0 mt-1 max-w-[70ch] text-[12.5px] text-subtle">
                    {{ issue.description }}
                </p>
                <div class="mt-2 text-[12.5px]">
                    <b class="text-ink">Rule:</b> {{ issue.rule }}
                </div>
                <Link
                    v-if="issue.audience && issue.count > 0"
                    :href="audienceIndex({ query: { filter: issue.audience } })"
                    class="mt-2 inline-block text-[12.5px] font-medium text-ink"
                    >See them in the Audience</Link
                >
            </div>
        </div>
    </div>
    <div
        v-else
        class="grid grid-cols-2 gap-3.5 max-[900px]:grid-cols-1"
        aria-hidden="true"
    >
        <div
            v-for="n in 6"
            :key="n"
            class="h-32 animate-pulse rounded-card bg-surface-2"
        ></div>
    </div>
    <AppDialog
        v-model:open="dialogOpen"
        title="Verify emails"
        description="Sends the next unchecked contact emails to the verifier now, outside the daily schedule."
    >
        <form class="flex flex-col gap-3.5" @submit.prevent="verify">
            <FormField
                v-slot="field"
                label="Number of emails"
                required
                :error="form.errors.count"
                :hint="`Up to ${format(MAX_PER_TASK)} per task. The daily limit does not apply here.`"
            >
                <input
                    :id="field.id"
                    v-model.number="form.count"
                    type="number"
                    min="1"
                    :max="MAX_PER_TASK"
                    inputmode="numeric"
                    :class="fieldClasses"
                    required
                    aria-required="true"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border border-line bg-surface-2 px-3 py-1 text-[12.5px] text-fg"
                    @click="form.count = dailyCredits"
                >
                    Daily credits left
                    <span class="num">({{ format(dailyCredits) }})</span>
                </button>
                <button
                    type="button"
                    class="rounded-full border border-line bg-surface-2 px-3 py-1 text-[12.5px] text-fg"
                    @click="form.count = emailVerification?.daily_limit ?? 1000"
                >
                    Daily limit
                    <span class="num"
                        >({{
                            format(emailVerification?.daily_limit ?? 1000)
                        }})</span
                    >
                </button>
            </div>
            <p
                v-if="fromInstant > 0"
                class="m-0 rounded-lg bg-sand-soft px-3 py-2 text-[12.5px] text-sand"
            >
                This is <span class="num">{{ format(fromInstant) }}</span> more
                than your daily credits left. The verifier will spend instant
                credits for them.
            </p>
            <div>
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="form.processing || !form.count"
                >
                    {{
                        form.processing
                            ? "Sending…"
                            : `Verify ${format(form.count ?? 0)} emails`
                    }}
                </button>
            </div>
        </form>
    </AppDialog>
</template>
