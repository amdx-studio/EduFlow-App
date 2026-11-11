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
});

const name = useId();
</script>

<template>
    <fieldset :disabled="disabled">
        <legend class="block text-sm font-medium text-slate-900" :class="{ 'sr-only': legendHidden }">{{ legend }}</legend>

        <div v-if="variant === 'card'" class="mt-2 space-y-2">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex min-h-10 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 text-sm transition-colors duration-150"
                :class="model === option.value ? 'border-brand-600 bg-brand-50' : 'border-slate-300 hover:bg-slate-50'"
            >
                <BaseRadio v-model="model" :value="option.value" :name="name" />
                <span>{{ option.label }}</span>
            </label>
        </div>

        <div v-else class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm">
            <label v-for="option in options" :key="option.value" class="inline-flex min-h-10 cursor-pointer items-center gap-2">
                <BaseRadio v-model="model" :value="option.value" :name="name" />
                {{ option.label }}
            </label>
        </div>

        <p v-if="error" class="mt-1 text-sm text-danger-700" role="alert">{{ error }}</p>
    </fieldset>
</template>