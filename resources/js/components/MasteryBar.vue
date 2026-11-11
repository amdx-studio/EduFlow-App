<script setup>
import { computed } from 'vue';

const props = defineProps({
    percent: { type: Number, required: true },
    level: { type: String, default: 'insufficient' },
    label: { type: String, default: 'Penguasaan' },
});

const FILL = {
    mastered: 'bg-success-600',
    developing: 'bg-warning-500',
    not_mastered: 'bg-danger-600',
    insufficient: 'bg-slate-400',
};

const width = computed(() => Math.min(100, Math.max(0, props.percent)));
const text = computed(() => `${props.percent.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`);
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="h-2 w-24 shrink-0 overflow-hidden rounded bg-slate-200 sm:w-32" role="progressbar"
            :aria-label="label" :aria-valuenow="Math.round(width)" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded" :class="FILL[level] ?? 'bg-slate-400'" :style="{ width: `${width}%` }"></div>
        </div>
        <span class="text-sm tabular-nums">{{ text }}</span>
    </div>
</template>