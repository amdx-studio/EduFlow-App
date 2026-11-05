<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import AnswerReview from '../components/AnswerReview.vue';
import ResultTable from '../components/ResultTable.vue';
import { errorMessage } from '../lib/errors';

const route = useRoute();

const result = ref(null);
const quiz = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const wib = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

function formatTime(iso) {
    return iso ? `${wib.format(new Date(iso))} WIB` : '-';
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
            class="text-sm text-indigo-700 hover:underline">
            &larr; Kembali ke kuis
        </RouterLink>
        <RouterLink v-else to="/courses" class="text-sm text-indigo-700 hover:underline">&larr; Daftar kelas</RouterLink>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat hasil...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Hasil tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="result" class="mt-3 space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h1 class="text-lg font-bold">{{ quiz?.title ?? 'Hasil kuis' }}</h1>
                <p class="text-sm text-slate-600">
                    Percobaan ke-{{ result.attempt.attempt_no }} &middot; dikirim {{ formatTime(result.attempt.submitted_at) }}
                </p>
                <p class="mt-4 text-4xl font-bold tabular-nums">{{ formatScore(result.attempt.score_percent) }}</p>
                <p class="text-sm text-slate-600">
                    {{ result.attempt.earned_points }} dari {{ result.attempt.possible_points }} poin
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Penguasaan per konsep</h2>
                <div class="mt-3">
                    <ResultTable :concepts="result.concepts" />
                </div>
            </div>

            <div>
                <h2 class="font-semibold">Tinjauan jawaban</h2>
                <div class="mt-3">
                    <AnswerReview :answers="result.answers" />
                </div>
            </div>
        </div>
    </section>
</template>
