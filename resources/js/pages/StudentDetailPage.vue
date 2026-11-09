<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import LevelBadge from '../components/LevelBadge.vue';
import MasteryBar from '../components/MasteryBar.vue';
import RemediationCard from '../components/RemediationCard.vue';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';

const route = useRoute();

const data = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data: res } = await http.get(`/api/courses/${route.params.courseId}/students/${route.params.studentId}`);
        data.value = res.data;
    } catch (e) {
        data.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

function score(value) {
    return `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

onMounted(load);
watch(() => [route.params.courseId, route.params.studentId], load);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${route.params.courseId}/students`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar siswa
        </RouterLink>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat detail siswa...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Siswa tidak ditemukan di kelas ini.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="data" class="mt-3 space-y-5">
            <div>
                <h1 class="text-lg font-bold">{{ data.student.name }}</h1>
                <p class="text-sm text-slate-600">{{ data.student.email }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Riwayat percobaan</h2>
                <p v-if="!data.attempts.length" class="mt-2 text-sm text-slate-600">Belum ada percobaan yang dikirim.</p>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Kuis</th>
                                <th class="px-3 py-2 font-semibold">Percobaan</th>
                                <th class="px-3 py-2 font-semibold">Skor</th>
                                <th class="px-3 py-2 font-semibold">Dikirim</th>
                                <th class="px-3 py-2 font-semibold">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in data.attempts" :key="a.id" class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    {{ a.quiz.title }}
                                    <span v-if="a.quiz.kind === 'reassessment'" class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs">Asesmen ulang</span>
                                </td>
                                <td class="px-3 py-2">{{ a.attempt_no }}</td>
                                <td class="px-3 py-2">{{ score(a.score_percent) }} ({{ a.earned_points }}/{{ a.possible_points }})</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ formatDateTime(a.submitted_at) }}</td>
                                <td class="px-3 py-2">
                                    <RouterLink :to="`/attempts/${a.id}/result`" class="text-indigo-700 underline">Lihat</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-for="q in data.latest_concepts" :key="q.attempt_id" class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Konsep pada percobaan terakhir: {{ q.quiz.title }}</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Konsep</th>
                                <th class="px-3 py-2 font-semibold">Benar/total</th>
                                <th class="px-3 py-2 font-semibold">Penguasaan</th>
                                <th class="px-3 py-2 font-semibold">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in q.concepts" :key="c.concept_id" class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ c.concept }}</td>
                                <td class="px-3 py-2">{{ c.correct }}/{{ c.total }}</td>
                                <td class="px-3 py-2"><MasteryBar :percent="c.percent" :level="c.level" :label="c.concept" /></td>
                                <td class="px-3 py-2"><LevelBadge :level="c.level" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Remedial</h2>
                <p v-if="!data.remediation.length" class="mt-2 text-sm text-slate-600">Belum ada remedial untuk siswa ini.</p>
                <ul v-else class="mt-3 space-y-3">
                    <li v-for="r in data.remediation" :key="r.id">
                        <RemediationCard :item="r" :course-id="route.params.courseId" :percent="r.mastery_percent" />
                    </li>
                </ul>
            </div>

            <div v-if="data.comparisons.length" class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Perbandingan sebelum dan sesudah</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="c in data.comparisons" :key="c.quiz.id" class="flex flex-wrap items-center gap-2">
                        <span>{{ c.quiz.title }}</span>
                        <span class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="c.status === 'complete' ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'">
                            {{ c.status === 'complete' ? 'Data lengkap' : 'Belum lengkap' }}
                        </span>
                        <RouterLink v-if="c.status === 'complete'"
                            :to="`/courses/${route.params.courseId}/quizzes/${c.quiz.id}/comparison?student_id=${data.student.id}`"
                            class="text-indigo-700 underline">
                            Lihat perbandingan
                        </RouterLink>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
