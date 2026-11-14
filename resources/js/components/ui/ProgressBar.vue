<script setup>
import { computed } from 'vue';

// Bilah progres netral (bukan Skala Penguasaan): jangan dipakai untuk menampilkan persen penguasaan.
const props = defineProps({
    value: { type: Number, required: true },
    max: { type: Number, default: 100 },
    label: { type: String, required: true },
    size: { type: String, default: 'md' },
});

const pct = computed(() => (props.max > 0 ? Math.min(100, Math.max(0, (props.value / props.max) * 100)) : 0));
</script>

<template>
    <div
        role="progressbar"
        :aria-label="label"
        :aria-valuenow="value"
        aria-valuemin="0"
        :aria-valuemax="max"
        class="w-full overflow-hidden rounded-full bg-kapur-200"
        :class="size === 'sm' ? 'h-1.5' : 'h-2.5'"
    >
        <div class="h-full rounded-full bg-samudra-600 transition-[width] duration-300 ease-standard" :style="{ width: `${pct}%` }"></div>
    </div>
</template>
