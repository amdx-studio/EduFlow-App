<script setup>
import { computed } from 'vue';
import GlyphLevel from './domain/GlyphLevel.vue';

const props = defineProps({
    level: { type: String, required: true },
});

// Label dan glyph selalu tampil bersama; warna bukan satu-satunya pembeda (§3.1 prinsip 5).
const META = {
    mastered: { label: 'Dikuasai', box: 'bg-level-mastered-tint text-level-mastered-text' },
    developing: { label: 'Berkembang', box: 'bg-level-developing-tint text-level-developing-text' },
    not_mastered: { label: 'Belum dikuasai', box: 'bg-level-not-mastered-tint text-level-not-mastered-text' },
    insufficient: { label: 'Data kurang', box: 'bg-level-insufficient-tint text-level-insufficient-text' },
};

const meta = computed(() => META[props.level] ?? { label: props.level, box: META.insufficient.box });
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full py-0.5 pr-3 pl-2 text-caption font-semibold" :class="meta.box">
        <GlyphLevel :level="level" :size="16" />
        {{ meta.label }}
    </span>
</template>
