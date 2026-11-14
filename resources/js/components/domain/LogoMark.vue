<script setup>
// Tanda logo (§3.4): tiga bilah 40/70/100%. Di permukaan gelap bilah teratas berwarna emas.
defineProps({
    dark: { type: Boolean, default: false },
    size: { type: Number, default: 28 },
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 28 28" fill="none" aria-hidden="true" class="shrink-0">
        <rect x="0" y="3" width="11.2" height="5.5" rx="2.75" :fill="dark ? 'var(--color-samudra-300)' : 'var(--color-samudra-300)'" />
        <rect x="0" y="11.25" width="19.6" height="5.5" rx="2.75" :fill="dark ? 'var(--color-samudra-200)' : 'var(--color-samudra-500)'" />
        <rect x="0" y="19.5" width="28" height="5.5" rx="2.75" :fill="dark ? 'var(--color-emas)' : 'var(--color-samudra-700)'" />
    </svg>
</template>
