<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    size: { type: Number, default: 36 },
});
const initial = computed(() => (props.name.trim()[0] ?? '?').toUpperCase());
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-samudra-100 font-semibold text-samudra-800"
        :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.4)}px` }"
        aria-hidden="true"
    >{{ initial }}</span>
</template>
