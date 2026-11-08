<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;
const quizId = route.params.quizId;

const quiz = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const starting = ref(false);

const activeAttempt = computed(() => quiz.value?.attempts?.find((a) => a.status === 'in_progress') ?? null);

const wib = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

function formatTime(iso) {
    return iso ? `${wib.format(new Date(iso))} WIB` : '-';
}

function formatScore(value) {
    return value === null || value === undefined
        ? '-'
        : `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

function kindLabel(kind) {
    return kind === 'reassessment' ? 'Asesmen ulang' : 'Asesmen';
}

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/quizzes/${quizId}`);
        quiz.value = data.data;
    } catch (e) {
        quiz.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

async function start() {
    starting.value = true;
    try {
        const { data } = await http.post(`/api/quizzes/${quizId}/attempts`);
        router.push(`/attempts/${data.data.id}`);
    } catch (e) {
        // 409 bila gate terkunci (mis. batas percobaan): pesan datang dari API.
        toast.error(errorMessage(e));
        await load();
    } finally {
        starting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${courseId}/quizzes`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar kuis
        </RouterLink>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat kuis...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Kuis tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="quiz" class="mt-3 space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="text-lg font-bold">{{ quiz.title }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ kindLabel(quiz.kind) }}</span>
                    <span>Percobaan terpakai: {{ quiz.attempts_used }} dari {{ quiz.max_attempts }}</span>
                    <RouterLink v-if="quiz.kind === 'reassessment'" :to="`/courses/${courseId}/quizzes/${quizId}/comparison`"
                        class="text-indigo-700 underline">
                        Lihat perbandingan
                    </RouterLink>
                </div>

                <div v-if="quiz.gate.status === 'available'" class="mt-4">
                    <button type="button" :disabled="starting"
                        class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        @click="start">
                        {{ starting ? 'Memuat...' : (activeAttempt ? 'Lanjutkan kuis' : 'Mulai kuis') }}
                    </button>
                    <p v-if="activeAttempt" class="mt-2 text-sm text-slate-600">
                        Anda memiliki percobaan ke-{{ activeAttempt.attempt_no }} yang belum dikirim.
                    </p>
                </div>

                <div v-else class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                    <p class="font-semibold">Kuis terkunci</p>
                    <p>{{ quiz.gate.reason }}</p>
                    <ul v-if="quiz.gate.pending?.length" class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="item in quiz.gate.pending" :key="item.id">
                            {{ item.concept }}: {{ item.lesson.title }}
                        </li>
                    </ul>
                    <RouterLink v-if="quiz.gate.pending?.length" :to="`/courses/${courseId}/remediation`"
                        class="mt-2 inline-block text-indigo-700 underline">
                        Buka Remedial Saya
                    </RouterLink>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="font-semibold">Riwayat percobaan</h3>
                <p v-if="quiz.attempts.length === 0" class="mt-2 text-sm text-slate-600">Belum ada percobaan.</p>
                <div v-else class="mt-2 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-600">
                                <th class="py-2 pr-4 font-medium">Percobaan</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Skor</th>
                                <th class="py-2 pr-4 font-medium">Waktu kirim</th>
                                <th class="py-2 font-medium">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attempt in quiz.attempts" :key="attempt.id" class="border-b border-slate-100">
                                <td class="py-2 pr-4">Ke-{{ attempt.attempt_no }}</td>
                                <td class="py-2 pr-4">{{ attempt.status === 'submitted' ? 'Terkirim' : 'Sedang dikerjakan' }}</td>
                                <td class="py-2 pr-4">{{ formatScore(attempt.score_percent) }}</td>
                                <td class="py-2 pr-4">{{ formatTime(attempt.submitted_at) }}</td>
                                <td class="py-2">
                                    <RouterLink v-if="attempt.status === 'submitted'" :to="`/attempts/${attempt.id}/result`"
                                        class="text-indigo-700 underline">Lihat hasil</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>
