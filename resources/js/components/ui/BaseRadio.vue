<script setup>
const model = defineModel({ type: [String, Number, Boolean], default: null });

defineProps({
    value: { type: [String, Number, Boolean], required: true },
    name: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <input
        v-model="model"
        type="radio"
        :value="value"
        :name="name || undefined"
        :disabled="disabled"
        class="h-4 w-4 shrink-0 accent-brand-600"
    />
</template>