<script setup>
// Baris angka tipografis (K-5, AL-9): angka serif besar dengan label kecil, tanpa kotak.
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div>
        <p class="font-serif text-num-l font-medium tracking-[-0.015em] text-ink tabular-nums">{{ value }}</p>
        <p class="mt-1 text-small font-semibold text-kapur-600">{{ label }}</p>
        <p v-if="hint" class="text-caption text-kapur-500">{{ hint }}</p>
    </div>
</template>
