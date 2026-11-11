<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import LevelDistribution from '../components/LevelDistribution.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseSelect from '../components/ui/BaseSelect.vue';
import DataTable from '../components/ui/DataTable.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import StatCard from '../components/ui/StatCard.vue';
import { errorMessage } from '../lib/errors';

const route = useRoute();

const data = ref(null);
const selectedQuizId = ref('');
const loading = ref(true);
const error = ref('');

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
        <div v-if="loading && !data" class="space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-10 w-64" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Skeleton class="h-24" />
                <Skeleton class="h-24" />
                <Skeleton class="h-24" />
            </div>
            <Skeleton class="h-48 w-full" />
            <span class="sr-only">Memuat dashboard...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Dashboard tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load(selectedQuizId)">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="data" class="space-y-5">
            <div class="flex flex-wrap items-end gap-3">
                <FormField label="Kuis dasar" v-slot="{ id }">
                    <BaseSelect :id="id" v-model="selectedQuizId" :disabled="loading || !data.quizzes.length" @change="onSelect">
                        <option v-if="!data.quizzes.length" value="">Belum ada kuis terbit</option>
                        <option v-for="q in data.quizzes" :key="q.id" :value="String(q.id)">{{ q.title }}</option>
                    </BaseSelect>
                </FormField>
                <p v-if="loading" class="pb-2 text-sm text-slate-500">Memuat...</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <StatCard label="Siswa terdaftar" :value="data.totals.students_enrolled" />
                <StatCard label="Sudah mengerjakan" :value="data.totals.students_submitted" />
                <StatCard label="Remedial selesai" :value="pct(data.totals.assignments_completed_percent)"
                    :hint="`${data.totals.assignments_completed} dari ${data.totals.assignments_total} penugasan`" />
            </div>

            <EmptyState
                v-if="!data.quiz"
                title="Belum ada data kuis dasar"
                description="Belum ada siswa yang mengirim kuis dasar. Data muncul setelah ada percobaan yang dikirim."
                icon="clipboard"
            />

            <template v-else>
                <InlineAlert v-if="data.resource_gaps.length" tone="warning" title="Konsep tanpa materi remedial">
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="g in data.resource_gaps" :key="g.concept_id">
                            {{ g.concept }}: {{ g.students_count }} siswa lemah, belum ada materi remedial terbit.
                        </li>
                    </ul>
                </InlineAlert>

                <BaseCard>
                    <h2 class="text-lg font-semibold">Penguasaan rata-rata per konsep</h2>
                    <p v-if="!data.concepts.length" class="mt-2 text-sm text-slate-600">Belum ada data konsep.</p>
                    <ul v-else class="mt-3 space-y-5">
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
                                <div class="h-3 bg-brand-500"
                                    :style="{ width: `${Math.min(100, Math.max(0, c.average_percent ?? 0))}%` }"></div>
                            </div>
                            <LevelDistribution class="mt-2" :levels="c.levels" />
                        </li>
                    </ul>
                </BaseCard>

                <section>
                    <h2 class="text-lg font-semibold">Perlu perhatian</h2>
                    <p v-if="!data.attention.length" class="mt-2 text-sm text-slate-600">
                        Tidak ada siswa dengan konsep belum dikuasai pada percobaan terakhir.
                    </p>
                    <div v-else class="mt-3">
                        <DataTable label="Siswa yang perlu perhatian" caption="Siswa dengan konsep belum dikuasai">
                            <thead>
                                <tr>
                                    <th scope="col">Siswa</th>
                                    <th scope="col">Konsep belum dikuasai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in data.attention" :key="a.student.id">
                                    <td>
                                        <RouterLink :to="`/courses/${route.params.courseId}/students/${a.student.id}`"
                                            class="font-medium text-brand-700 hover:underline">{{ a.student.name }}</RouterLink>
                                    </td>
                                    <td>
                                        <span v-for="(c, i) in a.concepts" :key="c.concept_id">
                                            {{ c.concept }} ({{ pct(c.percent) }}){{ i < a.concepts.length - 1 ? ', ' : '' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>
                </section>

                <section v-for="cmp in data.comparison" :key="cmp.quiz.id">
                    <h2 class="text-lg font-semibold">Sebelum dan sesudah: {{ cmp.quiz.title }}</h2>
                    <p class="text-sm text-slate-600">{{ cmp.students_count }} siswa dengan data lengkap.</p>
                    <p v-if="!cmp.concepts.length" class="mt-2 text-sm text-slate-600">Belum ada konsep yang dapat dibandingkan.</p>
                    <div v-else class="mt-3">
                        <DataTable :label="`Perbandingan ${cmp.quiz.title}`" caption="Rata-rata sebelum dan sesudah per konsep">
                            <thead>
                                <tr>
                                    <th scope="col">Konsep</th>
                                    <th scope="col">Sebelum</th>
                                    <th scope="col">Sesudah</th>
                                    <th scope="col">Selisih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in cmp.concepts" :key="r.concept_id">
                                    <td>{{ r.concept }}</td>
                                    <td class="tabular-nums">{{ pct(r.before) }}</td>
                                    <td class="tabular-nums">{{ pct(r.after) }}</td>
                                    <td class="tabular-nums">{{ signed(r.delta) }}</td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">{{ cmp.note }}</p>
                </section>
            </template>
        </div>
    </section>
</template>