<script setup>
import BaseButton from './ui/BaseButton.vue';
import BaseCard from './ui/BaseCard.vue';
import BaseIcon from './ui/BaseIcon.vue';
import InlineAlert from './ui/InlineAlert.vue';

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
    <BaseCard>
        <section aria-label="Panel publikasi">
            <h3 class="font-semibold">Publikasi</h3>

            <template v-if="quiz.status === 'draft'">
                <p class="mt-1 text-sm text-slate-600">Kuis ini masih draf dan belum terlihat oleh siswa.</p>

                <InlineAlert v-if="errorMessages.length" class="mt-3" tone="danger" title="Kuis belum bisa diterbitkan:">
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                    </ul>
                </InlineAlert>

                <BaseButton class="mt-3" :loading="publishing" @click="$emit('publish')">
                    {{ publishing ? 'Memeriksa...' : 'Validasi & Terbitkan' }}
                </BaseButton>
            </template>

            <template v-else>
                <p class="mt-1 flex items-center gap-1 text-sm font-medium text-success-800">
                    <BaseIcon name="check-circle" size="sm" />Kuis sudah terbit.
                </p>

                <InlineAlert v-if="warnings.length" class="mt-3" tone="warning" title="Peringatan:">
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="warning in warnings" :key="warning.concept_id">{{ warning.message }}</li>
                    </ul>
                </InlineAlert>

                <BaseButton class="mt-3" variant="secondary" :loading="reverting" :disabled="quiz.attempts_count > 0" @click="$emit('revert')">
                    {{ reverting ? 'Memproses...' : 'Kembali ke draf' }}
                </BaseButton>
                <p v-if="quiz.attempts_count > 0" class="mt-2 text-sm text-slate-600">
                    Tidak dapat kembali ke draf karena sudah ada {{ quiz.attempts_count }} percobaan.
                </p>
            </template>
        </section>
    </BaseCard>
</template>