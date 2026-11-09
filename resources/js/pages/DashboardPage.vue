<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';

const route = useRoute();

const data = ref(null);
const selectedQuizId = ref('');
const loading = ref(true);
const error = ref('');

const levelLabels = {
    mastered: 'Dikuasai',
    developing: 'Berkembang',
    not_mastered: 'Belum dikuasai',
    insufficient: 'Data kurang',
};

async function load(quizId) {
    loading.value = true;
    error.value = '';
    try {
        const params = quizId ? { quiz_id: quizId } : {};
        const res = await http.get(`/api/courses/${route.params.courseId}/dashboard`, { params });
        data.value = res.data.data;
        selectedQuizId.value = data.value.quiz ? String(data.value.quiz.id) : '';
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

function onSelect() {
    load(selectedQuizId.value);
}

function pct(value) {
    return value === null || value === undefined ? '-' : `${Number(value).toFixed(1).replace('.', ',')}%`;
}

function signed(value) {
    const n = Number(value);
    return `${n > 0 ? '+' : ''}${n.toFixed(1).replace('.', ',')}`;
}

onMounted(() => load());
watch(() => route.params.courseId, () => load());
</script>

<template>
    <section>
        <p v-if="loading && !data" class="text-slate-600">Memuat dashboard...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load(selectedQuizId)">Coba lagi</button>
        </div>

        <div v-else-if="data" class="space-y-5">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="dash-quiz" class="block text-sm font-medium text-slate-700">Kuis dasar</label>
                    <select id="dash-quiz" v-model="selectedQuizId"
                        class="mt-1 min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                        :disabled="loading || !data.quizzes.length" @change="onSelect">
                        <option v-if="!data.quizzes.length" value="">Belum ada kuis terbit</option>
                        <option v-for="q in data.quizzes" :key="q.id" :value="String(q.id)">{{ q.title }}</option>
                    </select>
                </div>
                <p v-if="loading" class="pb-2 text-sm text-slate-500">Memuat...</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-600">Siswa terdaftar</p>
                    <p class="mt-1 text-2xl font-bold">{{ data.totals.students_enrolled }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-600">Sudah mengerjakan</p>
                    <p class="mt-1 text-2xl font-bold">{{ data.totals.students_submitted }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-600">Remedial selesai</p>
                    <p class="mt-1 text-2xl font-bold">{{ pct(data.totals.assignments_completed_percent) }}</p>
                    <p class="text-xs text-slate-500">
                        {{ data.totals.assignments_completed }} dari {{ data.totals.assignments_total }} penugasan
                    </p>
                </div>
            </div>

            <div v-if="!data.quiz"
                class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
                Belum ada siswa yang mengirim kuis dasar. Data muncul setelah ada percobaan yang dikirim.
            </div>

            <template v-else>
                <div v-if="data.resource_gaps.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4" role="alert">
                    <h2 class="font-semibold text-amber-900">Konsep tanpa materi remedial</h2>
                    <ul class="mt-2 list-disc pl-5 text-sm text-amber-900">
                        <li v-for="g in data.resource_gaps" :key="g.concept_id">
                            {{ g.concept }}: {{ g.students_count }} siswa lemah, belum ada materi remedial terbit.
                        </li>
                    </ul>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">Penguasaan rata-rata per konsep</h2>
                    <p v-if="!data.concepts.length" class="mt-2 text-sm text-slate-600">Belum ada data konsep.</p>
                    <ul v-else class="mt-3 space-y-4">
                        <li v-for="c in data.concepts" :key="c.concept_id">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-medium">{{ c.concept }}</span>
                                <span class="text-sm text-slate-700">
                                    {{ pct(c.average_percent) }}
                                    <span class="text-slate-500">({{ c.students_count }} siswa)</span>
                                </span>
                            </div>
                            <div class="mt-1 h-3 w-full overflow-hidden rounded bg-slate-100"
                                role="img" :aria-label="`Rata-rata ${c.concept}: ${pct(c.average_percent)}`">
                                <div class="h-3 bg-indigo-500"
                                    :style="{ width: `${Math.min(100, Math.max(0, c.average_percent ?? 0))}%` }"></div>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                                <span v-for="(label, key) in levelLabels" :key="key">
                                    {{ label }}: <strong>{{ c.levels[key] }}</strong>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">Perlu perhatian</h2>
                    <p v-if="!data.attention.length" class="mt-2 text-sm text-slate-600">
                        Tidak ada siswa dengan konsep belum dikuasai pada percobaan terakhir.
                    </p>
                    <div v-else class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Siswa</th>
                                    <th class="px-3 py-2 font-semibold">Konsep belum dikuasai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in data.attention" :key="a.student.id" class="border-t border-slate-100">
                                    <td class="px-3 py-2"><RouterLink :to="`/courses/${route.params.courseId}/students/${a.student.id}`" class="text-indigo-700 hover:underline">{{ a.student.name }}</RouterLink></td>
                                    <td class="px-3 py-2">
                                        <span v-for="(c, i) in a.concepts" :key="c.concept_id">
                                            {{ c.concept }} ({{ pct(c.percent) }}){{ i < a.concepts.length - 1 ? ', ' : '' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-for="cmp in data.comparison" :key="cmp.quiz.id" class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">Sebelum dan sesudah: {{ cmp.quiz.title }}</h2>
                    <p class="text-sm text-slate-600">{{ cmp.students_count }} siswa dengan data lengkap.</p>
                    <p v-if="!cmp.concepts.length" class="mt-2 text-sm text-slate-600">Belum ada konsep yang dapat dibandingkan.</p>
                    <div v-else class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Konsep</th>
                                    <th class="px-3 py-2 font-semibold">Sebelum</th>
                                    <th class="px-3 py-2 font-semibold">Sesudah</th>
                                    <th class="px-3 py-2 font-semibold">Selisih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in cmp.concepts" :key="r.concept_id" class="border-t border-slate-100">
                                    <td class="px-3 py-2">{{ r.concept }}</td>
                                    <td class="px-3 py-2">{{ pct(r.before) }}</td>
                                    <td class="px-3 py-2">{{ pct(r.after) }}</td>
                                    <td class="px-3 py-2">{{ signed(r.delta) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">{{ cmp.note }}</p>
                </div>
            </template>
        </div>
    </section>
</template>
