<script setup>
import { useToastStore } from '../stores/toast';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';

const toast = useToastStore();

const TONES = {
    success: { box: 'text-on-dark-success', icon: 'check-circle' },
    error: { box: 'text-on-dark-error', icon: 'alert-triangle' },
};
const tone = (type) => TONES[type] ?? TONES.success;
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <div
            v-for="item in toast.items"
            :key="item.id"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-control bg-samudra-900 px-4 py-3 text-small shadow-popover"
            :class="tone(item.type).box"
            :role="item.type === 'error' ? 'alert' : 'status'"
        >
            <BaseIcon :name="tone(item.type).icon" class="mt-0.5" />
            <span class="min-w-0 flex-1 text-white">{{ item.message }}</span>
            <BaseButton variant="ghost" size="icon" class="-my-1.5 -mr-2 !text-samudra-200 hover:!bg-samudra-800" aria-label="Tutup" @click="toast.dismiss(item.id)">
                <BaseIcon name="x" size="sm" />
            </BaseButton>
        </div>
    </div>
</template>