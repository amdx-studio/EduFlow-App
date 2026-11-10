<script setup>
defineProps({
    tone: { type: String, default: 'neutral' },
});

// Pola: latar 100, teks 900. Badge selalu berisi teks (warna bukan satu-satunya pembeda).
const TONES = {
    neutral: 'bg-slate-200 text-slate-800',
    brand: 'bg-brand-100 text-brand-900',
    success: 'bg-success-100 text-success-900',
    warning: 'bg-warning-100 text-warning-900',
    danger: 'bg-danger-100 text-danger-900',
    info: 'bg-info-100 text-info-900',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="TONES[tone] ?? TONES.neutral">
        <slot />
    </span>
</template>