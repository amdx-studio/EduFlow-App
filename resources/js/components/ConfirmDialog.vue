<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Ya, lanjutkan' },
    busy: { type: Boolean, default: false },
});
defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4">
        <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-lg" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <h2 id="confirm-title" class="text-lg font-bold">{{ title }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" :disabled="busy"
                    class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-50"
                    @click="$emit('cancel')">
                    Batal
                </button>
                <button type="button" :disabled="busy"
                    class="min-h-10 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    @click="$emit('confirm')">
                    {{ busy ? 'Memproses...' : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>