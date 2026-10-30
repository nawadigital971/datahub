<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import Chip from '@/components/Chip.vue';
import FormField from '@/components/FormField.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import Toggle from '@/components/Toggle.vue';
import { toast } from '@/composables/useToast';
import { fieldClasses } from '@/lib/fieldClasses';
import type { SelectOption } from '@/types';

const emirates: SelectOption<string>[] = [
    { value: 'dubai', label: 'Dubai', hint: '412,880' },
    { value: 'sharjah', label: 'Sharjah', hint: '198,204' },
    { value: 'abu-dhabi', label: 'Abu Dhabi', hint: '154,019' },
    { value: 'ajman', label: 'Ajman', hint: '61,337' },
    { value: 'rak', label: 'Ras Al Khaimah', hint: '28,410' },
    { value: 'fujairah', label: 'Fujairah', hint: '12,775' },
    { value: 'uaq', label: 'Umm Al Quwain', hint: '6,102' },
];

const areas: SelectOption<string>[] = [
    { value: 'international-city', label: 'International City' },
    { value: 'deira', label: 'ديرة' },
    { value: 'al-nahda', label: 'Al Nahda' },
    { value: 'jlt', label: 'Jumeirah Lakes Towers' },
];

const years: SelectOption<number>[] = Array.from({ length: 14 }, (_, i) => ({
    value: 2013 + i,
    label: String(2013 + i),
}));

const emirate = ref<string | null>(null);
const selectedAreas = ref<string[]>(['deira']);
const fromYear = ref<number | null>(2013);
const toYear = ref<number | null>(2026);
const phone = ref<string | null>(null);
const companyName = ref('');
const hideDnc = ref(true);
const hasMobile = ref(false);
const chips = ref([
    { label: 'Emirate', value: 'Dubai' },
    { label: 'Source', value: 'Layalina subscribers' },
    { label: 'Must', value: 'Has mobile' },
]);
</script>

<template>
    <Head title="Components" />

    <div class="grid gap-[18px] lg:grid-cols-[290px_minmax(0,1fr)]">
        <aside class="rounded-card border border-line bg-surface">
            <div class="border-b border-line px-3.5 py-3">
                <h3
                    class="m-0 mb-2 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
                >
                    Compliance
                </h3>
                <Toggle v-model="hideDnc" label="Hide do-not-contact" />
                <Toggle v-model="hasMobile" label="Mobile number" />
                <Toggle label="Disabled toggle" disabled />
            </div>
            <div class="px-3.5 py-3">
                <h3
                    class="m-0 mb-2 text-[11px] font-semibold tracking-[.08em] text-subtle uppercase"
                >
                    Data year
                </h3>
                <div class="flex items-center gap-2">
                    <AppSelect
                        v-model="fromYear"
                        :options="years"
                        :searchable="false"
                        size="sm"
                        aria-label="From year"
                    />
                    <span>to</span>
                    <AppSelect
                        v-model="toYear"
                        :options="years"
                        :searchable="false"
                        size="sm"
                        aria-label="To year"
                    />
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col gap-[18px]">
            <section>
                <SectionHeading
                    title="Chips"
                    description="Removable filter chips"
                />
                <div class="flex flex-wrap gap-1.5">
                    <Chip
                        v-for="(chip, index) in chips"
                        :key="chip.value"
                        :label="chip.label"
                        :value="chip.value"
                        @remove="chips.splice(index, 1)"
                    />
                </div>
            </section>

            <section
                class="rounded-card border border-line bg-surface px-4 py-3.5"
            >
                <SectionHeading
                    title="Form fields"
                    description="Required fields show *, the rest (optional)"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField v-slot="field" label="Emirate" required>
                        <AppSelect
                            :id="field.id"
                            v-model="emirate"
                            :options="emirates"
                            :required="field.required"
                            :described-by="field.describedBy"
                            placeholder="Choose an emirate"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Areas"
                        hint="Multi-select with Arabic-aware search"
                    >
                        <AppSelect
                            :id="field.id"
                            v-model="selectedAreas"
                            :options="areas"
                            multiple
                            :described-by="field.describedBy"
                        />
                    </FormField>
                    <FormField v-slot="field" label="Mobile" required>
                        <PhoneInput
                            :id="field.id"
                            v-model="phone"
                            :required="field.required"
                            :described-by="field.describedBy"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Company"
                        error="Company name is too long."
                    >
                        <input
                            :id="field.id"
                            v-model="companyName"
                            :class="fieldClasses"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.describedBy"
                            dir="auto"
                        />
                    </FormField>
                </div>
                <p class="mt-3 mb-0 font-mono text-xs text-subtle">
                    emirate={{ emirate ?? 'null' }} · areas={{
                        selectedAreas.join(',')
                    }}
                    · phone={{ phone ?? 'null' }}
                </p>
            </section>

            <section class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
                    @click="toast('Applied: Dubai mobiles')"
                >
                    Show toast
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-line px-3.5 py-2 text-[13px] font-semibold"
                    @click="toast('Server error. Please try again.', 'error')"
                >
                    Show error toast
                </button>
            </section>
        </div>
    </div>
</template>
