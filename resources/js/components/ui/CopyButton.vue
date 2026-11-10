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
            class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            @click="copy"
        >
            <BaseIcon :name="state === 'done' ? 'check' : 'copy'" size="sm" />
            <span>{{ state === 'done' ? 'Tersalin' : state === 'failed' ? 'Gagal, salin manual' : label }}</span>
        </button>
        <span class="sr-only" role="status">{{ state === 'done' ? 'Disalin' : state === 'failed' ? 'Gagal menyalin' : '' }}</span>
    </span>
</template>