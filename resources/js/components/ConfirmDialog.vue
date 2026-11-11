<script setup>
import BaseButton from './ui/BaseButton.vue';
import Modal from './ui/Modal.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Ya, lanjutkan' },
    variant: { type: String, default: 'danger' },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal
        :model-value="open"
        :title="title"
        size="sm"
        :busy="busy"
        @update:model-value="(value) => !value && emit('cancel')"
    >
        <p class="text-sm text-slate-600">{{ message }}</p>
        <template #footer>
            <BaseButton variant="secondary" :disabled="busy" data-autofocus @click="emit('cancel')">Batal</BaseButton>
            <BaseButton :variant="variant" :loading="busy" @click="emit('confirm')">
                {{ busy ? 'Memproses...' : confirmLabel }}
            </BaseButton>
        </template>
    </Modal>
</template>