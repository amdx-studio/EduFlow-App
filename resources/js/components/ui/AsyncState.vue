<script setup>
import BaseButton from './BaseButton.vue';
import EmptyState from './EmptyState.vue';
import InlineAlert from './InlineAlert.vue';

// Pola satu pintu untuk memuat / error / kosong / berisi (§8.4). Slot `loading` opsional (kerangka khusus halaman).
defineProps({
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Belum ada data' },
    emptyDescription: { type: String, default: '' },
    emptyIcon: { type: String, default: 'clipboard' },
    retryLabel: { type: String, default: 'Coba lagi' },
});
defineEmits(['retry']);
</script>

<template>
    <div v-if="loading" role="status" aria-live="polite">
        <span class="sr-only">Memuat...</span>
        <slot name="loading">
            <div class="space-y-3" aria-hidden="true">
                <div class="h-5 w-1/3 animate-pulse rounded-control bg-kapur-200"></div>
                <div class="h-16 animate-pulse rounded-control bg-kapur-200"></div>
                <div class="h-16 animate-pulse rounded-control bg-kapur-200"></div>
            </div>
        </slot>
    </div>

    <div v-else-if="error" class="space-y-3">
        <InlineAlert tone="danger" title="Gagal memuat.">{{ error }}</InlineAlert>
        <BaseButton variant="secondary" @click="$emit('retry')">{{ retryLabel }}</BaseButton>
    </div>

    <EmptyState v-else-if="empty" :title="emptyTitle" :description="emptyDescription" :icon="emptyIcon">
        <slot name="empty-actions" />
    </EmptyState>

    <slot v-else />
</template>
