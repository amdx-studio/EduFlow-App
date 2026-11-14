<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import AddRemediationForm from '../components/AddRemediationForm.vue';
import Avatar from '../components/domain/Avatar.vue';
import ComparisonScale from '../components/domain/ComparisonScale.vue';
import MasteryBar from '../components/MasteryBar.vue';
import RemediationCard from '../components/RemediationCard.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import Modal from '../components/ui/Modal.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { activeCourse } from '../composables/useShell';
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
const showForm = ref(false); // modal di bawah 1280 px
const wide = ref(typeof window !== 'undefined' && window.matchMedia('(min-width: 1280px)').matches);
let mq = null;
const onMq = (e) => {
    wide.value = e.matches;
    if (e.matches) {
        showForm.value = false;
    }
};

// API-41 hanya memuat konsep untuk percobaan terakhir per kuis, jadi formulir dibatasi pada percobaan itu.
const attemptOptions = computed(() => data.value?.latest_concepts ?? []);

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
        showForm.value = false;
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
    mq = window.matchMedia('(min-width: 1280px)');
    mq.addEventListener('change', onMq);
});
onBeforeUnmount(() => mq?.removeEventListener('change', onMq));
watch(() => [route.params.courseId, route.params.studentId], load);

const crumbs = computed(() => [
    { label: 'Kelas', to: { name: 'courses' } },
    { label: activeCourse.value?.title ?? 'Kelas', to: `/courses/${route.params.courseId}/lessons` },
    { label: 'Siswa', to: `/courses/${route.params.courseId}/students` },
    { label: data.value?.student.name ?? 'Detail siswa' },
]);
</script>

<template>
    <section>
        <PageHeader :title="data?.student.name ?? 'Detail siswa'" :description="data?.student.email ?? ''" :breadcrumbs="crumbs" />

        <div v-if="loading" class="mt-8 space-y-8" role="status" aria-busy="true">
            <div v-for="n in 3" :key="n" class="space-y-3"><Skeleton class="h-6 w-48" /><Skeleton class="h-24 w-full" /></div>
            <span class="sr-only">Memuat detail siswa...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-8" as="h2" title="Siswa tidak ditemukan di kelas ini" icon="user">
            <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/students`">Daftar siswa</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" class="mt-8" tone="danger" title="Detail siswa tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <div v-else-if="data" class="mt-8 grid gap-10 xl:grid-cols-12">
            <div class="space-y-12 xl:col-span-8">
                <!-- Riwayat percobaan -->
                <section aria-labelledby="riwayat-title">
                    <h2 id="riwayat-title" class="font-serif text-h2 font-medium text-ink">Riwayat percobaan</h2>
                    <p v-if="!data.attempts.length" class="mt-3 text-body text-kapur-600">Belum ada percobaan.</p>
                    <ul v-else class="mt-4 border-t border-line">
                        <li v-for="a in data.attempts" :key="a.id" class="flex min-h-14 flex-wrap items-center gap-x-5 gap-y-1 border-b border-line py-3">
                            <span class="min-w-0 flex-1">
                                <span class="text-small font-semibold text-ink">{{ a.quiz.title }}</span>
                                <Badge v-if="a.quiz.kind === 'reassessment'" tone="remedial" class="ml-2">Asesmen ulang</Badge>
                                <span class="block text-caption text-kapur-500">Percobaan ke-{{ a.attempt_no }} &middot; {{ formatDateTime(a.submitted_at) }} WIB</span>
                            </span>
                            <span class="font-serif text-h3 tabular-nums text-ink">{{ score(a.score_percent) }}</span>
                            <span class="w-14 text-caption tabular-nums text-kapur-500">{{ a.earned_points }}/{{ a.possible_points }}</span>
                            <RouterLink :to="`/attempts/${a.id}/result`" class="text-small font-semibold text-samudra-600 underline">Lihat hasil</RouterLink>
                        </li>
                    </ul>
                </section>

                <!-- Penguasaan percobaan terakhir -->
                <section v-for="q in data.latest_concepts" :key="q.attempt_id" :aria-label="`Penguasaan pada ${q.quiz.title}`">
                    <h2 class="font-serif text-h2 font-medium text-ink">Penguasaan terakhir</h2>
                    <h3 class="mt-1 text-h3 font-semibold text-kapur-600">{{ q.quiz.title }}</h3>
                    <ul class="mt-4 border-t border-line">
                        <li v-for="(c, i) in q.concepts" :key="c.concept_id" class="grid gap-2 border-b border-line py-4 md:grid-cols-[11rem_1fr] md:items-center md:gap-6">
                            <div>
                                <p class="text-small font-semibold text-ink">{{ c.concept }}</p>
                                <p class="text-caption text-kapur-500">{{ c.correct }} dari {{ c.total }} benar</p>
                            </div>
                            <MasteryBar
                                :percent="Number(c.percent)"
                                :level="c.level"
                                :label="`Penguasaan ${c.concept}`"
                                :weak-threshold="c.weak_threshold_used ?? null"
                                :mastery-threshold="c.mastery_threshold_used ?? null"
                                :threshold-labels="i === 0"
                                size="lg"
                            />
                        </li>
                    </ul>
                </section>

                <!-- Remedial -->
                <section aria-labelledby="remedial-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="remedial-title" class="font-serif text-h2 font-medium text-ink">Remedial</h2>
                        <BaseButton v-if="attemptOptions.length && !wide" variant="secondary" @click="showForm = true">Tambah remedial</BaseButton>
                    </div>
                    <p v-if="!data.remediation.length" class="mt-3 text-body text-kapur-600">Belum ada remedial.</p>
                    <ul v-else class="mt-4 space-y-5">
                        <li v-for="r in data.remediation" :key="r.id">
                            <RemediationCard :item="r" :course-id="route.params.courseId" :percent="r.mastery_percent" />
                        </li>
                    </ul>
                </section>

                <!-- Perbandingan -->
                <section v-if="data.comparisons.length" aria-labelledby="cmp-title">
                    <h2 id="cmp-title" class="font-serif text-h2 font-medium text-ink">Perbandingan</h2>
                    <InlineAlert class="mt-3" tone="info">Perubahan skor, bukan bukti sebab-akibat.</InlineAlert>
                    <ul class="mt-4 border-t border-line">
                        <li v-for="c in data.comparisons" :key="c.quiz.id" class="flex flex-wrap items-center gap-3 border-b border-line py-4">
                            <span class="min-w-0 flex-1 text-small font-semibold text-ink">{{ c.quiz.title }}</span>
                            <Badge :tone="c.status === 'complete' ? 'success' : 'neutral'">{{ c.status === 'complete' ? 'Data lengkap' : 'Belum lengkap' }}</Badge>
                            <RouterLink
                                v-if="c.status === 'complete'"
                                :to="`/courses/${route.params.courseId}/quizzes/${c.quiz.id}/comparison?student_id=${data.student.id}`"
                                class="text-small font-semibold text-samudra-600 underline"
                            >Lihat perbandingan</RouterLink>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Tambah remedial: well sticky di layar lebar -->
            <aside v-if="attemptOptions.length && wide" class="xl:col-span-4">
                <div class="rounded-tile bg-well p-6 xl:sticky xl:top-6">
                    <h2 class="font-serif text-h2 font-medium text-ink">Tambah remedial</h2>
                    <p class="mt-1 mb-5 text-small text-kapur-600">Rekomendasi sistem tidak dapat dihapus. Remedial yang Anda tambahkan muncul di Remedial Saya milik siswa.</p>
                    <AddRemediationForm v-model="form" :attempt-options="attemptOptions" :lessons="lessons" :errors="formErrors" :saving="saving" @submit="submitRemedial" />
                </div>
            </aside>
        </div>

        <!-- Di bawah 1280 px: modal (bottom sheet di mobile) -->
        <Modal v-model="showForm" title="Tambah remedial" description="Rekomendasi sistem tidak dapat dihapus. Remedial yang Anda tambahkan muncul di Remedial Saya milik siswa." :busy="saving">
            <AddRemediationForm v-model="form" :attempt-options="attemptOptions" :lessons="lessons" :errors="formErrors" :saving="saving" @submit="submitRemedial" />
        </Modal>
    </section>
</template>
