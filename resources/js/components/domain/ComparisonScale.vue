<script setup>
import { computed } from 'vue';

// Skala Perbandingan (§3.3, §6.9): penanda sebelum (cincin) dan sesudah (terisi) pada satu trek,
// dengan ruas di antaranya. API-39 tidak memuat ambang, jadi skala tampil tanpa zona (VF-04).
const props = defineProps({
    before: { type: Number, required: true },
    after: { type: Number, required: true },
    trend: { type: String, default: 'unchanged' },
    label: { type: String, default: 'Perubahan penguasaan' },
});

const clamp = (n) => Math.min(100, Math.max(0, n));
const b = computed(() => clamp(props.before));
const a = computed(() => clamp(props.after));
const left = computed(() => Math.min(b.value, a.value));
const span = computed(() => Math.abs(a.value - b.value));
const SEG = {
    improved: 'bg-samudra-600',
    declined: 'bg-level-not-mastered-fill',
    unchanged: 'bg-kapur-400',
};
const DOT = {
    improved: 'bg-samudra-600',
    declined: 'bg-level-not-mastered-fill',
    unchanged: 'bg-kapur-400',
};
</script>

<template>
    <div class="relative h-5 w-full" role="img" :aria-label="`${label}: sebelum ${Math.round(before)} persen, sesudah ${Math.round(after)} persen`">
        <div class="absolute inset-x-0 top-1/2 h-2.5 -translate-y-1/2 rounded-full bg-kapur-200"></div>
        <div class="absolute top-1/2 h-2.5 -translate-y-1/2 rounded-full" :class="SEG[trend] ?? SEG.unchanged" :style="{ left: `${left}%`, width: `${span}%` }"></div>
        <span class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 bg-white" :class="trend === 'declined' ? 'border-level-not-mastered-fill' : trend === 'improved' ? 'border-samudra-600' : 'border-kapur-400'" :style="{ left: `${b}%` }"></span>
        <span class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full" :class="DOT[trend] ?? DOT.unchanged" :style="{ left: `${a}%` }"></span>
    </div>
</template>
