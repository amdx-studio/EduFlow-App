<script setup>
import { computed } from 'vue';
import { ICONS } from './icons';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'md' },
    label: { type: String, default: '' },
});

const SIZES = { sm: 'h-4 w-4', md: 'h-5 w-5', lg: 'h-6 w-6' };

const body = computed(() => {
    if (import.meta.env.DEV && !(props.name in ICONS)) {
        console.warn(`[BaseIcon] ikon tidak dikenal: ${props.name}`);
    }
    return ICONS[props.name] ?? '';
});
</script>

<template>
    <svg
        class="inline-block shrink-0"
        :class="SIZES[size] ?? SIZES.md"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :role="label ? 'img' : undefined"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : 'true'"
        v-html="body"
    ></svg>
</template>