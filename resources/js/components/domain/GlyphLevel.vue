<script setup>
import { computed } from 'vue';

// Glyph Penguasaan (§3.2): bentuk menyatakan level, tidak hanya warna. Selalu dipasangkan dengan teks level.
const props = defineProps({
    level: { type: String, default: 'insufficient' },
    size: { type: Number, default: 20 },
});

const FILL = {
    mastered: 'var(--color-level-mastered-fill)',
    developing: 'var(--color-level-developing-fill)',
    not_mastered: 'var(--color-level-not-mastered-fill)',
    insufficient: 'var(--color-level-insufficient-fill)',
};

const color = computed(() => FILL[props.level] ?? FILL.insufficient);
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 20 20" fill="none" class="inline-block shrink-0" aria-hidden="true">
        <template v-if="level === 'mastered'">
            <circle cx="10" cy="10" r="9" :fill="color" />
            <path d="M6 10.4l2.8 2.8L14 7.6" stroke="#fff" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else-if="level === 'developing'">
            <circle cx="10" cy="10" r="8.125" fill="#fff" :stroke="color" stroke-width="1.75" />
            <path d="M10 10V2a8 8 0 0 0 0 16z" :fill="color" />
        </template>
        <template v-else-if="level === 'not_mastered'">
            <circle cx="10" cy="10" r="8.125" fill="#fff" :stroke="color" stroke-width="1.75" />
            <path d="M10 10V2a8 8 0 0 1 8 8z" :fill="color" />
        </template>
        <template v-else>
            <circle cx="10" cy="10" r="8.125" fill="#fff" :stroke="color" stroke-width="1.75" stroke-dasharray="3 3" />
        </template>
    </svg>
</template>
