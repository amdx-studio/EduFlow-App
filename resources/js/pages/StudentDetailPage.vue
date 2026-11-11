<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import LevelBadge from '../components/LevelBadge.vue';
import MasteryBar from '../components/MasteryBar.vue';
import RemediationCard from '../components/RemediationCard.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseSelect from '../components/ui/BaseSelect.vue';
import BaseTextarea from '../components/ui/BaseTextarea.vue';
import DataTable from '../components/ui/DataTable.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
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
        <BaseButton variant="ghost" size="sm" :to="`/courses/${route.params.courseId}/students`">
            <BaseIcon name="arrow-left" size="sm" />Daftar siswa
        </BaseButton>

        <div v-if="loading" class="mt-3 space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-7 w-1/3" />
            <Skeleton class="h-32 w-full" />
            <Skeleton class="h-32 w-full" />
            <span class="sr-only">Memuat detail siswa...</span>
        </div>

        <div v-else-if="notFound" class="mt-3">
            <EmptyState as="h1" title="Siswa tidak ditemukan di kelas ini" icon="user" />
        </div>

        <InlineAlert v-else-if="error" class="mt-3" tone="danger" title="Detail siswa tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="data" class="mt-3 space-y-6">
            <div>
                <h1 class="text-2xl font-semibold">{{ data.student.name }}</h1>
                <p class="text-sm text-slate-600">{{ data.student.email }}</p>
            </div>

            <section>
                <h2 class="text-lg font-semibold">Riwayat percobaan</h2>
                <p v-if="!data.attempts.length" class="mt-2 text-sm text-slate-600">Belum ada percobaan yang dikirim.</p>
                <div v-else class="mt-3">
                    <DataTable label="Riwayat percobaan siswa" caption="Riwayat percobaan yang sudah dikirim">
                        <thead>
                            <tr>
                                <th scope="col">Kuis</th>
                                <th scope="col">Percobaan</th>
                                <th scope="col">Skor</th>
                                <th scope="col">Dikirim</th>
                                <th scope="col">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in data.attempts" :key="a.id">
                                <td>
                                    {{ a.quiz.title }}
                                    <Badge v-if="a.quiz.kind === 'reassessment'" class="ml-1">Asesmen ulang</Badge>
                                </td>
                                <td>{{ a.attempt_no }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ score(a.score_percent) }} ({{ a.earned_points }}/{{ a.possible_points }})</td>
                                <td class="whitespace-nowrap">{{ formatDateTime(a.submitted_at) }}</td>
                                <td>
                                    <RouterLink :to="`/attempts/${a.id}/result`" class="font-medium text-brand-700 underline">Lihat</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </section>

            <section v-for="q in data.latest_concepts" :key="q.attempt_id">
                <h2 class="text-lg font-semibold">Konsep pada percobaan terakhir: {{ q.quiz.title }}</h2>
                <div class="mt-3">
                    <DataTable :label="`Konsep pada ${q.quiz.title}`" caption="Penguasaan per konsep pada percobaan terakhir">
                        <thead>
                            <tr>
                                <th scope="col">Konsep</th>
                                <th scope="col">Benar/total</th>
                                <th scope="col">Penguasaan</th>
                                <th scope="col">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in q.concepts" :key="c.concept_id">
                                <td>{{ c.concept }}</td>
                                <td>{{ c.correct }}/{{ c.total }}</td>
                                <td><MasteryBar :percent="c.percent" :level="c.level" :label="c.concept" /></td>
                                <td><LevelBadge :level="c.level" /></td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold">Remedial</h2>
                <p v-if="!data.remediation.length" class="mt-2 text-sm text-slate-600">Belum ada remedial untuk siswa ini.</p>
                <ul v-else class="mt-3 space-y-3">
                    <li v-for="r in data.remediation" :key="r.id">
                        <RemediationCard :item="r" :course-id="route.params.courseId" :percent="r.mastery_percent" />
                    </li>
                </ul>
            </section>

            <BaseCard v-if="attemptOptions.length">
                <form novalidate @submit.prevent="submitRemedial">
                    <h2 class="text-lg font-semibold">Tambah remedial</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Rekomendasi sistem tidak dapat dihapus. Remedial yang Anda tambahkan muncul di Remedial Saya milik siswa.
                    </p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <FormField label="Percobaan (terakhir per kuis)" required v-slot="{ id }">
                            <BaseSelect :id="id" v-model="form.attemptId" required @change="form.conceptId = ''">
                                <option value="" disabled>Pilih percobaan</option>
                                <option v-for="q in attemptOptions" :key="q.attempt_id" :value="String(q.attempt_id)">{{ q.quiz.title }}</option>
                            </BaseSelect>
                        </FormField>
                        <FormField label="Konsep" required :error="formErrors.concept_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseSelect :id="id" v-model="form.conceptId" required :disabled="!form.attemptId"
                                :aria-describedby="describedby" :invalid="invalid">
                                <option value="" disabled>Pilih konsep</option>
                                <option v-for="c in conceptOptions" :key="c.concept_id" :value="String(c.concept_id)">
                                    {{ c.concept }} ({{ score(c.percent) }})
                                </option>
                            </BaseSelect>
                        </FormField>
                        <FormField class="sm:col-span-2" label="Materi remedial" required :error="formErrors.lesson_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseSelect :id="id" v-model="form.lessonId" required
                                :aria-describedby="describedby" :invalid="invalid">
                                <option value="" disabled>{{ lessons.length ? 'Pilih materi' : 'Belum ada materi remedial terbit' }}</option>
                                <option v-for="l in lessons" :key="l.id" :value="String(l.id)">{{ l.title }}</option>
                            </BaseSelect>
                        </FormField>
                        <FormField class="sm:col-span-2" label="Catatan (opsional)" :error="formErrors.note?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseTextarea :id="id" v-model="form.note" :rows="3" maxlength="1000"
                                :aria-describedby="describedby" :invalid="invalid" />
                        </FormField>
                    </div>
                    <div class="mt-4">
                        <BaseButton type="submit" :loading="saving" :disabled="!form.attemptId || !form.conceptId || !form.lessonId">
                            {{ saving ? 'Menyimpan...' : 'Tambah remedial' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <section v-if="data.comparisons.length">
                <h2 class="text-lg font-semibold">Perbandingan sebelum dan sesudah</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="c in data.comparisons" :key="c.quiz.id" class="flex flex-wrap items-center gap-2">
                        <span>{{ c.quiz.title }}</span>
                        <Badge :tone="c.status === 'complete' ? 'success' : 'neutral'">
                            {{ c.status === 'complete' ? 'Data lengkap' : 'Belum lengkap' }}
                        </Badge>
                        <RouterLink v-if="c.status === 'complete'"
                            :to="`/courses/${route.params.courseId}/quizzes/${c.quiz.id}/comparison?student_id=${data.student.id}`"
                            class="font-medium text-brand-700 underline">
                            Lihat perbandingan
                        </RouterLink>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</template>