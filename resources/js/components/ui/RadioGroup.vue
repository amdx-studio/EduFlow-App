<script setup>
import { useId } from 'vue';
import BaseRadio from './BaseRadio.vue';

const model = defineModel({ type: [String, Number, Boolean], default: null });

defineProps({
    legend: { type: String, required: true },
    options: { type: Array, required: true },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    variant: { type: String, default: 'inline' },
    legendHidden: { type: Boolean, default: false },
    lettered: { type: Boolean, default: false },
});

const name = useId();
const letter = (i) => String.fromCharCode(65 + i);
</script>

<template>
    <fieldset :disabled="disabled">
        <legend class="block text-small font-semibold text-ink" :class="{ 'sr-only': legendHidden }">{{ legend }}</legend>

        <div v-if="variant === 'card'" class="mt-2 space-y-3">
            <label
                v-for="(option, i) in options"
                :key="option.value"
                class="flex min-h-16 cursor-pointer items-center gap-4 rounded-[16px] border px-5 py-4 text-body-l transition-colors duration-120 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-samudra-500"
                :class="model === option.value ? 'border-2 border-samudra-700 bg-samudra-50' : 'border-line-control hover:bg-kapur-50'"
            >
                <input v-model="model" type="radio" class="sr-only" :value="option.value" :name="name" />
                <span
                    v-if="lettered"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-small font-semibold"
                    :class="model === option.value ? 'bg-samudra-700 text-white' : 'bg-kapur-100 text-ink'"
                    aria-hidden="true"
                >{{ letter(i) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-ink">{{ option.label }}</span>
                    <span v-if="option.description" class="mt-1 block text-small text-kapur-600">{{ option.description }}</span>
                </span>
            </label>
        </div>

        <div v-else class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-body">
            <label v-for="option in options" :key="option.value" class="inline-flex min-h-10 cursor-pointer items-center gap-2">
                <BaseRadio v-model="model" :value="option.value" :name="name" />
                {{ option.label }}
            </label>
        </div>

        <p v-if="error" class="mt-2 text-caption text-level-not-mastered-text" role="alert">{{ error }}</p>
    </fieldset>
</template>
