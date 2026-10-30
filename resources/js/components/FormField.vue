<script setup lang="ts">
import { computed, useId } from 'vue';
import FormLabel from '@/components/FormLabel.vue';

const props = defineProps<{
    label: string;
    id?: string;
    required?: boolean;
    error?: string;
    hint?: string;
}>();

const generatedId = useId();
const fieldId = computed(() => props.id ?? generatedId);
const hintId = computed(() => `${fieldId.value}-hint`);
const errorId = computed(() => `${fieldId.value}-error`);
const describedBy = computed(
    () =>
        [props.hint ? hintId.value : null, props.error ? errorId.value : null]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <FormLabel :for="fieldId" :required="required">{{ label }}</FormLabel>
        <slot
            :id="fieldId"
            :required="required"
            :invalid="Boolean(error)"
            :described-by="describedBy"
        />
        <p v-if="hint" :id="hintId" class="m-0 text-xs text-subtle">
            {{ hint }}
        </p>
        <p v-if="error" :id="errorId" class="m-0 text-xs text-bad">
            {{ error }}
        </p>
    </div>
</template>
