<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ExportButtons from '../components/ExportButtons.vue';
import LevelDistribution from '../components/LevelDistribution.vue';
import PrintButton from '../components/PrintButton.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import Avatar from '../components/domain/Avatar.vue';
import ComparisonScale from '../components/domain/ComparisonScale.vue';
import ConceptChip from '../components/domain/ConceptChip.vue';
import GlyphLevel from '../components/domain/GlyphLevel.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import MasteryBar from '../components/MasteryBar.vue';
import ProgressBar from '../components/ui/ProgressBar.vue';
import { activeCourse } from '../composables/useShell';
import BaseSelect from '../components/ui/BaseSelect.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';

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

const LEGEND = [
    { level: 'mastered', label: 'Dikuasai' },
    { level: 'developing', label: 'Berkembang' },
    { level: 'not_mastered', label: 'Belum dikuasai' },
    { level: 'insufficient', label: 'Data kurang' },
];

// Urutan tampilan: rata-rata terendah lebih dulu; konsep tanpa rata-rata (semua data kurang) di akhir.
const sortedConcepts = computed(() =>
    [...(data.value?.concepts ?? [])].sort((a, b) => (a.average_percent ?? Infinity) - (b.average_percent ?? Infinity)),
);

const MAX_CHIPS = 2;
const trendOf = (delta) => (Number(delta) > 0 ? 'improved' : Number(delta) < 0 ? 'declined' : 'unchanged');
const printedAt = computed(() => formatDateTime(new Date().toISOString()));
const noRemedial = computed(() => (data.value?.totals.assignments_total ?? 0) === 0);
</script>

<template>
    <section>
        <!-- Blok judul cetak (hanya tampil saat dicetak) -->
        <div class="mb-6 hidden print:block">
            <p class="font-serif text-h2 font-medium">{{ activeCourse?.title }}</p>
            <p v-if="data?.quiz" class="text-body">{{ data.quiz.title }}</p>
            <p class="text-caption">Dicetak {{ printedAt }} WIB</p>
        </div>

        <h2 class="sr-only">Dashboard kelas</h2>

        <div v-if="loading && !data" class="space-y-8" role="status" aria-busy="true">
            <Skeleton class="h-12 w-80" />
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3"><Skeleton class="h-20" /><Skeleton class="h-20" /><Skeleton class="h-20" /></div>
            <Skeleton v-for="n in 4" :key="n" class="h-24 w-full" />
            <span class="sr-only">Memuat dashboard...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Dashboard tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load(selectedQuizId)">Muat ulang</BaseButton></div>
        </InlineAlert>

        <div v-else-if="data" class="space-y-10">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
                <div class="flex w-full flex-wrap items-end gap-3 md:w-auto">
                    <FormField class="w-full md:w-80" label="Kuis dasar" v-slot="{ id }">
                        <BaseSelect :id="id" v-model="selectedQuizId" :disabled="loading || !data.quizzes.length" @change="onSelect">
                            <option v-if="!data.quizzes.length" value="">Belum ada kuis dasar</option>
                            <option v-for="q in data.quizzes" :key="q.id" :value="String(q.id)">{{ q.title }}</option>
                        </BaseSelect>
                    </FormField>
                    <p v-if="loading" class="pb-3 text-small text-kapur-500" role="status">Memuat...</p>
                </div>
                <div class="flex items-center gap-2">
                    <ExportButtons v-if="data.quiz" :quiz-id="data.quiz.id" />
                    <PrintButton />
                </div>
            </div>

            <!-- StatRow -->
            <dl class="grid grid-cols-1 gap-6 divide-y divide-line md:grid-cols-3 md:divide-x md:divide-y-0" aria-label="Ringkasan kelas">
                <div class="pt-6 first:pt-0 md:px-6 md:pt-0 md:first:pl-0">
                    <dd class="font-serif text-num-l font-medium tabular-nums text-ink">{{ data.totals.students_enrolled }}</dd>
                    <dt class="text-small font-semibold text-kapur-600">Siswa terdaftar</dt>
                </div>
                <div class="pt-6 md:px-6 md:pt-0">
                    <dd class="font-serif text-num-l font-medium tabular-nums text-ink">{{ data.totals.students_submitted }}</dd>
                    <dt class="text-small font-semibold text-kapur-600">Sudah mengerjakan</dt>
                    <p class="text-caption text-kapur-500">dari {{ data.totals.students_enrolled }} siswa</p>
                </div>
                <div class="pt-6 md:px-6 md:pt-0">
                    <dd class="font-serif text-num-l font-medium tabular-nums text-ink">{{ noRemedial ? '—' : pct(data.totals.assignments_completed_percent) }}</dd>
                    <dt class="text-small font-semibold text-kapur-600">Remedial selesai</dt>
                    <template v-if="noRemedial">
                        <p class="text-caption text-kapur-500">Belum ada penugasan remedial</p>
                    </template>
                    <template v-else>
                        <ProgressBar class="mt-2 h-2!" :value="data.totals.assignments_completed" :max="data.totals.assignments_total" label="Remedial selesai" />
                        <p class="mt-1 text-caption text-kapur-500">{{ data.totals.assignments_completed }} dari {{ data.totals.assignments_total }} penugasan</p>
                    </template>
                </div>
            </dl>

            <EmptyState
                v-if="!data.quiz"
                title="Belum ada hasil untuk dianalisis"
                description="Dashboard terisi setelah ada siswa yang mengirim kuis dasar yang sudah terbit."
                icon="clipboard"
            >
                <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/quizzes`">Buka tab Kuis</BaseButton>
            </EmptyState>

            <template v-else>
                <!-- Peringatan sumber belajar (FR-022) -->
                <InlineAlert v-if="data.resource_gaps.length" tone="warning" title="Konsep tanpa materi remedial">
                    <ul class="mt-1 space-y-1">
                        <li v-for="g in data.resource_gaps" :key="g.concept_id" class="flex flex-wrap items-center gap-2">
                            <ConceptChip :name="g.concept" />
                            <span>{{ g.students_count }} siswa lemah, belum ada materi remedial terbit.</span>
                        </li>
                    </ul>
                    <RouterLink :to="`/courses/${route.params.courseId}/lessons/new`" class="mt-2 inline-block font-semibold underline">Tambah materi remedial</RouterLink>
                </InlineAlert>

                <div class="grid gap-10 xl:grid-cols-12">
                    <!-- Peta penguasaan kelas -->
                    <section class="xl:col-span-7" aria-labelledby="peta-title">
                        <h2 id="peta-title" class="font-serif text-h2 font-medium text-ink">Peta penguasaan kelas</h2>
                        <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-caption text-kapur-600" aria-label="Legenda level">
                            <li v-for="l in LEGEND" :key="l.level" class="inline-flex items-center gap-1.5"><GlyphLevel :level="l.level" :size="16" />{{ l.label }}</li>
                        </ul>

                        <p v-if="!sortedConcepts.length" class="mt-4 text-body text-kapur-600">Belum ada data konsep.</p>
                        <ul v-else class="mt-4 border-t border-line">
                            <li v-for="c in sortedConcepts" :key="c.concept_id" class="break-inside-avoid border-b border-line py-5">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="text-h3 font-semibold text-ink">{{ c.concept }}</span>
                                    <span class="text-caption text-kapur-500">{{ c.students_count }} siswa</span>
                                </div>
                                <div class="mt-3">
                                    <MasteryBar v-if="c.average_percent !== null && c.average_percent !== undefined" :percent="Number(c.average_percent)" level="average" :show-level="false" :label="`Rata-rata ${c.concept}`" size="lg" animate />
                                    <MasteryBar v-else :percent="0" level="insufficient" :label="`Rata-rata ${c.concept}`" size="lg" />
                                </div>
                                <LevelDistribution class="mt-3" :levels="c.levels" />
                            </li>
                        </ul>
                    </section>

                    <!-- Perlu perhatian -->
                    <aside class="max-md:order-first xl:col-span-5" aria-labelledby="attn-title">
                        <div class="rounded-tile bg-well p-5 md:p-6 xl:sticky xl:top-6">
                            <h2 id="attn-title" class="font-serif text-h2 font-medium text-ink">Perlu perhatian</h2>
                            <div v-if="!data.attention.length" class="mt-4 flex items-center gap-3">
                                <GlyphLevel level="mastered" :size="24" />
                                <p class="text-body text-ink">Tidak ada siswa yang perlu perhatian.</p>
                            </div>
                            <ul v-else class="mt-2 divide-y divide-line">
                                <li v-for="a in data.attention" :key="a.student.id">
                                    <RouterLink :to="`/courses/${route.params.courseId}/students/${a.student.id}`" class="flex items-center gap-3 py-3 hover:text-samudra-700">
                                        <Avatar :name="a.student.name" :size="36" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-small font-semibold text-ink">{{ a.student.name }}</span>
                                            <span class="mt-1 flex flex-wrap items-center gap-1">
                                                <ConceptChip v-for="c in a.concepts.slice(0, MAX_CHIPS)" :key="c.concept_id" :name="c.concept" class="max-w-45 truncate" />
                                                <span v-if="a.concepts.length > MAX_CHIPS" class="text-caption font-semibold text-kapur-500">+{{ a.concepts.length - MAX_CHIPS }}</span>
                                            </span>
                                        </span>
                                        <BaseIcon name="chevron-right" size="sm" class="text-kapur-400" />
                                    </RouterLink>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>

                <!-- Perbandingan asesmen ulang -->
                <section v-if="data.comparison.length" aria-labelledby="cmp-title">
                    <h2 id="cmp-title" class="font-serif text-h2 font-medium text-ink">Perbandingan asesmen ulang</h2>
                    <InlineAlert class="mt-3" tone="info">Perubahan skor, bukan bukti sebab-akibat.</InlineAlert>

                    <div v-for="cmp in data.comparison" :key="cmp.quiz.id" class="mt-6">
                        <h3 class="text-h3 font-semibold text-ink">{{ cmp.quiz.title }}</h3>
                        <p class="text-caption text-kapur-500">{{ cmp.students_count }} siswa dengan data lengkap.</p>
                        <p v-if="!cmp.concepts.length" class="mt-2 text-body text-kapur-600">Belum ada konsep yang dapat dibandingkan.</p>
                        <ul v-else class="mt-3 border-t border-line">
                            <li v-for="r in cmp.concepts" :key="r.concept_id" class="grid gap-2 border-b border-line py-4 md:grid-cols-[12rem_1fr_6rem] md:items-center md:gap-6">
                                <span class="text-small font-semibold text-ink">{{ r.concept }}</span>
                                <ComparisonScale :before="Number(r.before)" :after="Number(r.after)" :trend="trendOf(r.delta)" :label="`Perubahan ${r.concept}`" />
                                <span class="text-small tabular-nums md:text-right" :class="trendOf(r.delta) === 'declined' ? 'text-level-not-mastered-text' : trendOf(r.delta) === 'improved' ? 'text-samudra-700' : 'text-kapur-600'">
                                    {{ pct(r.before) }} → {{ pct(r.after) }}
                                    <span class="block font-semibold">{{ signed(r.delta) }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>
            </template>
        </div>
    </section>
</template>
