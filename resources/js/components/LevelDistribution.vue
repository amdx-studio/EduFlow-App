<script setup>
import { computed } from 'vue';
import GlyphLevel from './domain/GlyphLevel.vue';

// Pita Sebaran (§6.9): distribusi siswa per level, dengan legenda glyph + teks + angka.
const props = defineProps({
    levels: { type: Object, required: true },
});

const SEGMENTS = [
    { key: 'mastered', label: 'Dikuasai', cls: 'bg-level-mastered-fill' },
    { key: 'developing', label: 'Berkembang', cls: 'bg-level-developing-fill' },
    { key: 'not_mastered', label: 'Belum dikuasai', cls: 'bg-level-not-mastered-fill' },
    { key: 'insufficient', label: 'Data kurang', cls: 'bg-level-insufficient-fill' },
];

const parts = computed(() => SEGMENTS.map((s) => ({ ...s, count: props.levels[s.key] ?? 0 })));
const total = computed(() => parts.value.reduce((sum, p) => sum + p.count, 0));
const summary = computed(() => parts.value.map((p) => `${p.label} ${p.count}`).join(', '));
</script>

<template>
    <div>
        <div v-if="total > 0" class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full" role="img" :aria-label="`Sebaran level: ${summary}`">
            <div v-for="p in parts.filter((x) => x.count > 0)" :key="p.key" :class="p.cls" :style="{ width: `${(p.count / total) * 100}%` }"></div>
        </div>
        <div v-else class="h-3 w-full rounded-full pattern-insufficient" role="img" aria-label="Belum ada data sebaran"></div>
        <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-caption text-kapur-600">
            <li v-for="p in parts" :key="p.key" class="inline-flex items-center gap-1.5">
                <GlyphLevel :level="p.key" :size="16" />
                {{ p.label }} <strong class="font-semibold text-ink tabular-nums">{{ p.count }}</strong>
            </li>
        </ul>
    </div>
</template>
