<script setup>
import { computed } from 'vue';

const props = defineProps({
    levels: { type: Object, required: true },
});

// Warna bukan satu-satunya pembeda: legenda berteks dan berangka (bagian 10.5).
const SEGMENTS = [
    { key: 'mastered', label: 'Dikuasai', cls: 'bg-success-600' },
    { key: 'developing', label: 'Berkembang', cls: 'bg-warning-500' },
    { key: 'not_mastered', label: 'Belum dikuasai', cls: 'bg-danger-600' },
    { key: 'insufficient', label: 'Data kurang', cls: 'bg-slate-400' },
];

const parts = computed(() => SEGMENTS.map((s) => ({ ...s, count: props.levels[s.key] ?? 0 })));
const total = computed(() => parts.value.reduce((sum, p) => sum + p.count, 0));
const summary = computed(() => parts.value.map((p) => `${p.label} ${p.count}`).join(', '));
</script>

<template>
    <div>
        <div v-if="total > 0" class="flex h-3 w-full overflow-hidden rounded bg-slate-100"
            role="img" :aria-label="`Distribusi level: ${summary}`">
            <div v-for="p in parts" :key="p.key" :class="p.cls" :style="{ width: `${(p.count / total) * 100}%` }"></div>
        </div>
        <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
            <li v-for="p in parts" :key="p.key" class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" :class="p.cls" aria-hidden="true"></span>
                {{ p.label }}: <strong>{{ p.count }}</strong>
            </li>
        </ul>
    </div>
</template>