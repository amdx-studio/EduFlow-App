<script setup>
import { ref } from 'vue';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';
import Badge from './ui/Badge.vue';
import InlineAlert from './ui/InlineAlert.vue';

// PublishPanel (§7.10): well berisi status, aturan, tombol terbit / kembali ke draf, serta hasil validasi.
// `errorItems`: [{ message, questionId|null }]; tautan menuju soal ditangani induk lewat `goto`.
defineProps({
    quiz: { type: Object, required: true },
    publishing: { type: Boolean, default: false },
    reverting: { type: Boolean, default: false },
    errorItems: { type: Array, default: () => [] },
    warnings: { type: Array, default: () => [] },
});
defineEmits(['publish', 'revert', 'goto']);

const errorBox = ref(null);

// Dipanggil induk setelah validasi gagal agar pembaca layar dan keyboard langsung ke ringkasan.
function focusErrors() {
    errorBox.value?.focus();
}
defineExpose({ focusErrors });
</script>

<template>
    <div class="rounded-tile bg-well p-5 md:p-6">
        <section aria-labelledby="publish-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="publish-title" class="font-serif text-h2 font-medium text-ink">Terbitkan</h2>
                <Badge :tone="quiz.status === 'draft' ? 'neutral' : 'success'">{{ quiz.status === 'draft' ? 'Draf' : 'Terbit' }}</Badge>
            </div>
            <p class="mt-2 text-small text-kapur-600">Soal hanya dapat diubah saat kuis berstatus draf.</p>

            <template v-if="quiz.status === 'draft'">
                <p class="mt-1 text-small text-kapur-600">Kuis draf belum terlihat oleh siswa.</p>

                <div
                    v-if="errorItems.length"
                    ref="errorBox"
                    tabindex="-1"
                    class="mt-4 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-samudra-500"
                >
                    <InlineAlert tone="danger" title="Kuis belum bisa diterbitkan">
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li v-for="item in errorItems" :key="`${item.questionId}-${item.message}`">
                                <button
                                    v-if="item.questionId"
                                    type="button"
                                    class="text-left font-semibold underline underline-offset-2"
                                    @click="$emit('goto', item.questionId)"
                                >{{ item.message }}</button>
                                <template v-else>{{ item.message }}</template>
                            </li>
                        </ul>
                    </InlineAlert>
                </div>

                <BaseButton class="mt-5 w-full max-md:hidden" :loading="publishing" @click="$emit('publish')">
                    {{ publishing ? 'Memeriksa...' : 'Validasi & Terbitkan' }}
                </BaseButton>
            </template>

            <template v-else>
                <InlineAlert class="mt-4" tone="info">Kuis terbit. Soal tidak dapat diubah.</InlineAlert>

                <InlineAlert v-if="warnings.length" class="mt-3" tone="warning" title="Peringatan">
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="warning in warnings" :key="warning.concept_id">{{ warning.message }}</li>
                    </ul>
                </InlineAlert>

                <BaseButton class="mt-5 w-full" variant="secondary" :loading="reverting" :disabled="quiz.attempts_count > 0" @click="$emit('revert')">
                    {{ reverting ? 'Memproses...' : 'Kembali ke draf' }}
                </BaseButton>
                <p v-if="quiz.attempts_count > 0" class="mt-3 flex items-start gap-2 text-small text-kapur-600">
                    <BaseIcon name="lock" size="sm" class="mt-0.5 shrink-0" />
                    Sudah ada percobaan siswa, sehingga kuis tidak dapat dikembalikan ke draf.
                </p>
            </template>
        </section>
    </div>
</template>
