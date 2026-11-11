<script setup>
import BaseCard from './BaseCard.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <BaseCard>
        <p class="text-sm text-slate-600">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ value }}</p>
        <p v-if="hint" class="text-xs text-slate-500">{{ hint }}</p>
    </BaseCard>
</template>