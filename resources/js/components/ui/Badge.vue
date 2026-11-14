<script setup>
defineProps({
    tone: { type: String, default: 'neutral' },
});

// Badge berbentuk pil; selalu berisi teks (warna bukan satu-satunya pembeda).
const TONES = {
    neutral: 'bg-kapur-100 text-kapur-600',
    brand: 'bg-samudra-100 text-samudra-800',
    success: 'bg-level-mastered-tint text-level-mastered-text',
    warning: 'bg-level-developing-tint text-level-developing-text',
    danger: 'bg-level-not-mastered-tint text-level-not-mastered-text',
    info: 'bg-info-tint text-info-text',
    remedial: 'bg-anggur-50 text-anggur-800',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-caption font-semibold" :class="TONES[tone] ?? TONES.neutral">
        <slot />
    </span>
</template>
