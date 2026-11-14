<script setup>
import { onBeforeUnmount, ref } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, default: 'Salin' },
});

const state = ref('idle');
let timer = null;

async function copy() {
    clearTimeout(timer);
    try {
        await navigator.clipboard.writeText(props.value);
        state.value = 'done';
    } catch {
        state.value = 'failed';
    }
    timer = setTimeout(() => {
        state.value = 'idle';
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <span class="inline-flex">
        <button
            type="button"
            class="inline-flex min-h-9 items-center gap-1.5 rounded-control border border-line-control bg-white px-3 py-1.5 text-caption font-semibold text-ink hover:bg-kapur-100"
            @click="copy"
        >
            <BaseIcon :name="state === 'done' ? 'check' : 'copy'" size="sm" />
            <span>{{ state === 'done' ? 'Tersalin' : state === 'failed' ? 'Gagal, salin manual' : label }}</span>
        </button>
        <span class="sr-only" role="status">{{ state === 'done' ? 'Disalin' : state === 'failed' ? 'Gagal menyalin' : '' }}</span>
    </span>
</template>