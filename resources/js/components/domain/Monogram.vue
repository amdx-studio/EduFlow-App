<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    size: { type: Number, default: 32 },
});

const letter = computed(() => (props.title.trim()[0] ?? '?').toUpperCase());
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-code bg-samudra-100 font-serif font-medium text-samudra-800"
        :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.5)}px` }"
        aria-hidden="true"
    >{{ letter }}</span>
</template>
