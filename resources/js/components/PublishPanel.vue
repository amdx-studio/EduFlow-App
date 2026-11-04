<script setup>
defineProps({
    quiz: { type: Object, required: true },
    publishing: { type: Boolean, default: false },
    reverting: { type: Boolean, default: false },
    errorMessages: { type: Array, default: () => [] },
    warnings: { type: Array, default: () => [] },
});
defineEmits(['publish', 'revert']);
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-4" aria-label="Panel publikasi">
        <h3 class="font-semibold">Publikasi</h3>

        <template v-if="quiz.status === 'draft'">
            <p class="mt-1 text-sm text-slate-600">Kuis ini masih draf dan belum terlihat oleh siswa.</p>

            <div v-if="errorMessages.length" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                <p class="font-semibold">Kuis belum bisa diterbitkan:</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                </ul>
            </div>

            <button type="button" :disabled="publishing"
                class="mt-3 min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                @click="$emit('publish')">
                {{ publishing ? 'Memeriksa...' : 'Validasi & Terbitkan' }}
            </button>
        </template>

        <template v-else>
            <p class="mt-1 text-sm font-medium text-green-800">&#10003; Kuis sudah terbit.</p>

            <div v-if="warnings.length" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                <p class="font-semibold">Peringatan:</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="warning in warnings" :key="warning.concept_id">{{ warning.message }}</li>
                </ul>
            </div>

            <button type="button" :disabled="reverting || quiz.attempts_count > 0"
                class="mt-3 min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-50"
                @click="$emit('revert')">
                {{ reverting ? 'Memproses...' : 'Kembali ke draf' }}
            </button>
            <p v-if="quiz.attempts_count > 0" class="mt-2 text-sm text-slate-600">
                Tidak dapat kembali ke draf karena sudah ada {{ quiz.attempts_count }} percobaan.
            </p>
        </template>
    </section>
</template>
