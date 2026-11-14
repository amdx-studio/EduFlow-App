<script setup>
import { useId } from 'vue';

// Segmented (§6.2): wadah Kapur 100 radius 12 padding 4; opsi tinggi 36 radius 8; role="radiogroup".
const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    label: { type: String, required: true },
});

const name = `seg-${useId()}`;
</script>

<template>
    <div role="radiogroup" :aria-label="label" class="inline-flex rounded-xl bg-kapur-100 p-1">
        <label v-for="option in options" :key="option.value" class="cursor-pointer">
            <input v-model="model" type="radio" :name="name" :value="option.value" class="peer sr-only" />
            <span
                class="inline-flex h-9 items-center rounded-lg px-4 text-small font-medium text-kapur-600 transition-colors hover:text-ink peer-checked:bg-white peer-checked:font-semibold peer-checked:text-ink peer-checked:ring-1 peer-checked:ring-kapur-200 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-samudra-600"
            >
                {{ option.label }}
            </span>
        </label>
    </div>
</template>
