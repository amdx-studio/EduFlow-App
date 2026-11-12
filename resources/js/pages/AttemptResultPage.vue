<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import AnswerReview from '../components/AnswerReview.vue';
import PrintButton from '../components/PrintButton.vue';
import ResultTable from '../components/ResultTable.vue';
import RemediationCard from '../components/RemediationCard.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';

const route = useRoute();

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
</script>

<template>
    <section>
        <RouterLink v-if="quiz" :to="`/courses/${quiz.course_id}/quizzes/${quiz.id}`"
            class="text-sm text-brand-700 hover:underline print:hidden">
            &larr; Kembali ke kuis
        </RouterLink>
        <RouterLink v-else to="/courses" class="text-sm text-brand-700 hover:underline print:hidden">&larr; Daftar kelas</RouterLink>

        <div v-if="loading" class="mt-3 space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-6 w-1/3" />
            <Skeleton class="h-10 w-1/4" />
            <Skeleton class="h-4 w-2/3" />
            <span class="sr-only">Memuat hasil...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-3" title="Hasil tidak ditemukan." icon="clipboard">
            <BaseButton variant="secondary" to="/courses">Daftar kelas</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-3">
            {{ error }}
            <BaseButton variant="secondary" size="sm" class="ml-2" @click="load">Coba lagi</BaseButton>
        </InlineAlert>

        <div v-else-if="result" class="mt-3 space-y-5">
            <div class="flex justify-end print:hidden"><PrintButton /></div>

            <BaseCard>
                <h1 class="text-lg font-semibold text-slate-900">{{ quiz?.title ?? 'Hasil kuis' }}</h1>
                <p class="text-sm text-slate-600">
                    Percobaan ke-{{ result.attempt.attempt_no }} &middot; dikirim {{ formatTime(result.attempt.submitted_at) }}
                </p>
                <p class="mt-4 text-4xl font-bold tabular-nums text-slate-900">{{ formatScore(result.attempt.score_percent) }}</p>
                <p class="text-sm text-slate-600">
                    {{ result.attempt.earned_points }} dari {{ result.attempt.possible_points }} poin
                </p>
            </BaseCard>

            <BaseCard>
                <h2 class="font-semibold text-slate-900">Penguasaan per konsep</h2>
                <div class="mt-3">
                    <ResultTable :concepts="result.concepts" />
                </div>
            </BaseCard>

            <BaseCard>
                <h2 class="font-semibold text-slate-900">Rekomendasi remedial</h2>
                <p v-if="!result.remediation.length && !result.remediation_gaps.length"
                    class="mt-3 text-sm text-slate-600">
                    Tidak ada konsep yang perlu remedial pada percobaan ini.
                </p>
                <ul v-if="result.remediation.length" class="mt-3 space-y-3">
                    <li v-for="item in result.remediation" :key="item.id">
                        <RemediationCard :item="item" :course-id="quiz?.course_id ?? null" />
                    </li>
                </ul>
                <InlineAlert v-if="result.remediation_gaps.length" tone="warning" class="mt-3">
                    <p v-for="gap in result.remediation_gaps" :key="gap.concept_id">
                        {{ gap.concept }}: Belum ada materi remedial untuk konsep ini; hubungi guru.
                    </p>
                </InlineAlert>
            </BaseCard>

            <div>
                <h2 class="font-semibold text-slate-900">Tinjauan jawaban</h2>
                <div class="mt-3">
                    <AnswerReview :answers="result.answers" />
                </div>
            </div>
        </div>
    </section>
</template>
