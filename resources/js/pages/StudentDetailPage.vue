<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import LevelBadge from '../components/LevelBadge.vue';
import MasteryBar from '../components/MasteryBar.vue';
import RemediationCard from '../components/RemediationCard.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { formatDateTime } from '../lib/format';
import { useToastStore } from '../stores/toast';

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

const toast = useToastStore();
const lessons = ref([]);
const form = ref({ attemptId: '', conceptId: '', lessonId: '', note: '' });
const formErrors = ref({});
const saving = ref(false);

// API-41 hanya memuat konsep untuk percobaan terakhir per kuis, jadi formulir dibatasi pada percobaan itu.
const attemptOptions = computed(() => data.value?.latest_concepts ?? []);
const conceptOptions = computed(
    () => attemptOptions.value.find((q) => String(q.attempt_id) === String(form.value.attemptId))?.concepts ?? [],
);

async function loadLessons() {
    try {
        const { data: res } = await http.get(`/api/courses/${route.params.courseId}/lessons`, { params: { kind: 'remedial' } });
        lessons.value = res.data.filter((l) => l.is_published);
    } catch {
        lessons.value = [];
    }
}

async function submitRemedial() {
    saving.value = true;
    formErrors.value = {};
    try {
        await http.post(`/api/attempts/${form.value.attemptId}/remediation`, {
            concept_id: Number(form.value.conceptId),
            lesson_id: Number(form.value.lessonId),
            note: form.value.note.trim() || null,
        });
        toast.success('Remedial ditambahkan.');
        form.value = { attemptId: '', conceptId: '', lessonId: '', note: '' };
        await load();
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    load();
    loadLessons();
});
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

                        <form v-if="attemptOptions.length" class="rounded-lg border border-slate-200 bg-white p-5" novalidate
                @submit.prevent="submitRemedial">
                <h2 class="font-semibold">Tambah remedial</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Rekomendasi sistem tidak dapat dihapus. Remedial yang Anda tambahkan muncul di Remedial Saya milik siswa.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="rm-attempt" class="block text-sm font-medium text-slate-700">Percobaan (terakhir per kuis)</label>
                        <select id="rm-attempt" v-model="form.attemptId" required
                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                            @change="form.conceptId = ''">
                            <option value="" disabled>Pilih percobaan</option>
                            <option v-for="q in attemptOptions" :key="q.attempt_id" :value="String(q.attempt_id)">{{ q.quiz.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="rm-concept" class="block text-sm font-medium text-slate-700">Konsep</label>
                        <select id="rm-concept" v-model="form.conceptId" required :disabled="!form.attemptId"
                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="" disabled>Pilih konsep</option>
                            <option v-for="c in conceptOptions" :key="c.concept_id" :value="String(c.concept_id)">
                                {{ c.concept }} ({{ score(c.percent) }})
                            </option>
                        </select>
                        <p v-if="formErrors.concept_id" class="mt-1 text-sm text-red-700">{{ formErrors.concept_id[0] }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="rm-lesson" class="block text-sm font-medium text-slate-700">Materi remedial</label>
                        <select id="rm-lesson" v-model="form.lessonId" required
                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="" disabled>{{ lessons.length ? 'Pilih materi' : 'Belum ada materi remedial terbit' }}</option>
                            <option v-for="l in lessons" :key="l.id" :value="String(l.id)">{{ l.title }}</option>
                        </select>
                        <p v-if="formErrors.lesson_id" class="mt-1 text-sm text-red-700">{{ formErrors.lesson_id[0] }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="rm-note" class="block text-sm font-medium text-slate-700">Catatan (opsional)</label>
                        <textarea id="rm-note" v-model="form.note" rows="3" maxlength="1000"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <p v-if="formErrors.note" class="mt-1 text-sm text-red-700">{{ formErrors.note[0] }}</p>
                    </div>
                </div>
                <button type="submit"
                    :disabled="saving || !form.attemptId || !form.conceptId || !form.lessonId"
                    class="mt-4 min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? 'Menyimpan...' : 'Tambah remedial' }}
                </button>
            </form>

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
