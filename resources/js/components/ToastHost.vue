<script setup>
import { useToastStore } from '../stores/toast';

const toast = useToastStore();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <div
            v-for="item in toast.items"
            :key="item.id"
            class="pointer-events-auto flex w-full max-w-sm items-start justify-between gap-3 rounded-md border px-4 py-3 text-sm shadow"
            :class="item.type === 'error' ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-300 bg-green-50 text-green-800'"
            role="status"
        >
            <span>{{ item.message }}</span>
            <button type="button" class="font-semibold" aria-label="Tutup" @click="toast.dismiss(item.id)">&times;</button>
        </div>
    </div>
</template>
