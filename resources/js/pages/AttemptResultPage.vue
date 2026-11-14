<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import AnswerReview from '../components/AnswerReview.vue';
import PrintButton from '../components/PrintButton.vue';
import ResultTable from '../components/ResultTable.vue';
import RemediationCard from '../components/RemediationCard.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import GlyphLevel from '../components/domain/GlyphLevel.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { useAuthStore } from '../stores/auth';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';

const route = useRoute();
const auth = useAuthStore();

const result = ref(null);
const quiz = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

function formatTime(iso) {
    return iso ? `${formatDateTime(iso)} WIB` : '-';
}

function formatScore(value) {
    return `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/attempts/${route.params.attemptId}/result`);
        result.value = data.data;

        // Judul kuis dan tautan kembali; hasil tetap ditampilkan bila ini gagal.
        try {
            const quizRes = await http.get(`/api/quizzes/${result.value.attempt.quiz_id}`);
            quiz.value = quizRes.data.data;
        } catch {
            quiz.value = null;
        }
    } catch (e) {
        result.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.attemptId, load);

const title = computed(() => (quiz.value ? `Hasil ${quiz.value.title}` : 'Hasil kuis'));
const crumbs = computed(() => {
    const list = [{ label: 'Kelas', to: { name: 'courses' } }];
    if (quiz.value) {
        list.push({ label: 'Kuis', to: `/courses/${quiz.value.course_id}/quizzes` });
        list.push({ label: quiz.value.title, to: `/courses/${quiz.value.course_id}/quizzes/${quiz.value.id}` });
    }
    if (result.value) {
        list.push({ label: `Hasil percobaan ${result.value.attempt.attempt_no}` });
    }
    return list;
});
const hasRemediation = computed(() => (result.value?.remediation.length ?? 0) > 0);
const noRemediationAtAll = computed(() => result.value && !result.value.remediation.length && !result.value.remediation_gaps.length);
</script>

<template>
    <section>
        <PageHeader :title="title" :breadcrumbs="crumbs">
            <template v-if="result" #actions>
                <PrintButton class="print:hidden" />
                <BaseButton
                    v-if="auth.isStudent && quiz"
                    :variant="hasRemediation ? 'primary' : 'secondary'"
                    :to="`/courses/${quiz.course_id}/remediation`"
                    class="print:hidden"
                >Buka Remedial Saya</BaseButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="mt-8 space-y-6" role="status" aria-busy="true">
            <Skeleton class="h-14 w-40" />
            <Skeleton class="h-4 w-1/3" />
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-16 w-full" />
            <span class="sr-only">Memuat hasil...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-8" title="Hasil tidak ditemukan." description="Percobaan ini tidak ada atau Anda tidak memiliki akses." icon="clipboard">
            <BaseButton variant="secondary" to="/courses">Daftar kelas</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-8" title="Hasil tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <div v-else-if="result" class="mt-8 space-y-12">
            <!-- Hero: angka skor -->
            <div>
                <p class="font-serif text-[44px] leading-[44px] font-medium tabular-nums text-ink md:text-num-xl md:leading-14">{{ formatScore(result.attempt.score_percent) }}</p>
                <p class="mt-2 text-body text-kapur-600">
                    {{ result.attempt.earned_points }} dari {{ result.attempt.possible_points }} poin
                    <span class="text-kapur-400" aria-hidden="true">&middot;</span>
                    Percobaan ke-{{ result.attempt.attempt_no }}
                </p>
                <p class="text-caption text-kapur-500">Dikirim {{ formatTime(result.attempt.submitted_at) }}</p>
            </div>

            <div>
                <h2 class="font-serif text-h2 font-medium text-ink">Penguasaan per konsep</h2>
                <div class="mt-4"><ResultTable :concepts="result.concepts" /></div>
            </div>

            <div>
                <h2 class="font-serif text-h2 font-medium text-ink">Rekomendasi remedial</h2>
                <div v-if="noRemediationAtAll" class="mt-4 flex items-center gap-3 rounded-tile bg-well p-5">
                    <GlyphLevel level="mastered" :size="28" />
                    <p class="text-body text-ink">Tidak ada konsep yang perlu dipelajari ulang.</p>
                </div>
                <ul v-if="result.remediation.length" class="mt-4 space-y-4">
                    <li v-for="item in result.remediation" :key="item.id">
                        <RemediationCard :item="item" :course-id="quiz?.course_id ?? null" />
                    </li>
                </ul>
                <InlineAlert v-if="result.remediation_gaps.length" tone="info" class="mt-4">
                    <p v-for="gap in result.remediation_gaps" :key="gap.concept_id">
                        Belum ada materi remedial untuk konsep {{ gap.concept }}; hubungi guru.
                    </p>
                </InlineAlert>
            </div>

            <div class="print:break-before-page">
                <h2 class="font-serif text-h2 font-medium text-ink">Tinjau jawaban</h2>
                <div class="mt-4"><AnswerReview :answers="result.answers" /></div>
            </div>
        </div>
    </section>
</template>
