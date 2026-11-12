<script setup>
import { useToastStore } from '../stores/toast';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';

const toast = useToastStore();

const TONES = {
    success: { box: 'border-success-200 bg-success-50 text-success-900', icon: 'check-circle' },
    error: { box: 'border-danger-200 bg-danger-50 text-danger-900', icon: 'alert-triangle' },
};
const tone = (type) => TONES[type] ?? TONES.success;
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <div
            v-for="item in toast.items"
            :key="item.id"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
            :class="tone(item.type).box"
            :role="item.type === 'error' ? 'alert' : 'status'"
        >
            <BaseIcon :name="tone(item.type).icon" class="mt-0.5" />
            <span class="min-w-0 flex-1">{{ item.message }}</span>
            <BaseButton variant="ghost" size="icon" class="-my-1.5 -mr-2" aria-label="Tutup" @click="toast.dismiss(item.id)">
                <BaseIcon name="x" size="sm" />
            </BaseButton>
        </div>
    </div>
</template>