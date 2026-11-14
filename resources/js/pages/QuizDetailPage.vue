<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import JalurBelajar from '../components/domain/JalurBelajar.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
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
const startError = ref(null); // { message, pending } dari 409 saat mulai (§7.11)
const comparisonStatus = ref(null); // 'incomplete' | lainnya | 'error' | null

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


// API-39 hanya relevan bagi asesmen ulang yang sudah dikerjakan; gagal tidak boleh merusak halaman.
async function loadComparisonStatus() {
    comparisonStatus.value = null;
    if (quiz.value?.kind !== 'reassessment' || !(quiz.value.attempts_used >= 1)) {
        return;
    }
    try {
        const { data } = await http.get(`/api/quizzes/${quizId}/comparison`);
        comparisonStatus.value = data.data?.status ?? 'ready';
    } catch {
        comparisonStatus.value = 'error';
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/quizzes/${quizId}`);
        quiz.value = data.data;
        await loadComparisonStatus();
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
    startError.value = null;
    try {
        const { data } = await http.post(`/api/quizzes/${quizId}/attempts`);
        router.push(`/attempts/${data.data.id}`);
    } catch (e) {
        // 409 bila gate terkunci: pesan dan pending[] datang dari API, tampil di dalam GatePanel.
        if (e.response?.status === 409) {
            startError.value = { message: errorMessage(e), pending: e.response.data?.pending ?? [] };
        } else {
            toast.error(errorMessage(e));
        }
        await load();
    } finally {
        starting.value = false;
    }
}

onMounted(load);

// Jalur Belajar (§7.11): status disusun hanya dari gate, attempts_used, dan status API-39.
const steps = computed(() => {
    const q = quiz.value;
    if (!q || q.kind !== 'reassessment') {
        return [];
    }
    const reason = q.gate?.reason ?? '';
    const available = q.gate?.status === 'available';
    const used = q.attempts_used ?? 0;
    const pendingN = q.gate?.pending?.length ?? 0;
    const needBase = reason.startsWith('Kerjakan kuis dasar');
    const needRemedial = reason.startsWith('Selesaikan remedial');
    const remedialRoute = `/courses/${courseId}/remediation`;

    const base = needBase
        ? { state: 'active', caption: 'Kerjakan lebih dulu' }
        : { state: 'done', caption: 'Selesai' };
    const remedial = needBase
        ? { state: 'todo', caption: 'Belum' }
        : needRemedial
            ? { state: 'active', caption: `${pendingN} materi belum selesai`, to: remedialRoute }
            : { state: 'done', caption: 'Selesai atau tidak diperlukan' };

    let reassess;
    if (used >= 1) {
        reassess = { state: 'done', caption: 'Selesai' };
    } else if (available) {
        reassess = { state: 'active', caption: 'Siap dikerjakan' };
    } else {
        reassess = { state: 'locked', caption: reason };
    }

    let comparison = { state: 'todo', caption: 'Belum' };
    if (used >= 1) {
        if (comparisonStatus.value === 'error') {
            comparison = { state: 'todo', caption: 'Tidak dapat dimuat' };
        } else if (comparisonStatus.value && comparisonStatus.value !== 'incomplete') {
            comparison = { state: 'active', caption: 'Lihat perbandingan', to: `/courses/${courseId}/quizzes/${quizId}/comparison` };
        }
    }

    return [
        { key: 'base', label: 'Kuis dasar', accent: 'primary', ...base },
        { key: 'remedial', label: 'Remedial', accent: 'remedial', ...remedial },
        { key: 'reassess', label: 'Asesmen ulang', accent: 'remedial', ...reassess },
        { key: 'comparison', label: 'Perbandingan', accent: 'primary', ...comparison },
    ];
});
</script>

<template>
    <section>
        <PageHeader
            :title="quiz?.title ?? 'Kuis'"
            :breadcrumbs="[{ label: 'Kuis', to: `/courses/${courseId}/quizzes` }, { label: quiz?.title ?? 'Kuis' }]"
        >
            <template v-if="quiz" #meta>
                <p class="mt-2 flex flex-wrap items-center gap-3 text-small text-kapur-600">
                    <Badge :tone="quiz.kind === 'reassessment' ? 'remedial' : 'neutral'">{{ quiz.kind === 'reassessment' ? 'Asesmen ulang' : 'Kuis dasar' }}</Badge>
                    <span>Percobaan {{ quiz.attempts_used }} dari {{ quiz.max_attempts }}</span>
                </p>
            </template>
        </PageHeader>

        <div v-if="loading" class="mt-8 space-y-6" role="status" aria-busy="true">
            <Skeleton class="h-40 w-full" />
            <Skeleton class="h-32 w-full" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-8" title="Kuis tidak ditemukan" description="Kuis ini tidak ada atau belum diterbitkan." icon="alert-triangle">
            <BaseButton variant="secondary" :to="`/courses/${courseId}/quizzes`">Daftar kuis</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" class="mt-8" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <div v-else-if="quiz" class="mt-8 grid gap-10 xl:grid-cols-12">
            <div class="space-y-10" :class="steps.length ? 'xl:col-span-8' : 'xl:col-span-12'">
                <!-- GatePanel -->
                <div class="rounded-tile bg-well p-6">
                    <InlineAlert v-if="startError" class="mb-4" tone="danger" :title="startError.message">
                        <ul v-if="startError.pending.length" class="mt-1 list-disc pl-5">
                            <li v-for="item in startError.pending" :key="item.id">{{ item.concept }}: {{ item.lesson?.title }}</li>
                        </ul>
                    </InlineAlert>

                    <template v-if="quiz.gate.status === 'available'">
                        <h2 class="font-serif text-h2 font-medium text-ink">{{ activeAttempt ? 'Lanjutkan kuis' : 'Mulai kuis' }}</h2>
                        <p class="mt-1 text-body text-kapur-600">
                            <template v-if="activeAttempt">Anda memiliki percobaan ke-{{ activeAttempt.attempt_no }} yang belum dikirim. </template>
                            Jawaban Anda tersimpan otomatis setiap kali memilih.
                        </p>
                        <BaseButton class="mt-5 max-md:w-full" size="lg" :loading="starting" @click="start">
                            {{ starting ? 'Memuat...' : (activeAttempt ? 'Lanjutkan kuis' : 'Mulai kuis') }}
                        </BaseButton>
                    </template>

                    <template v-else>
                        <div class="flex items-start gap-3">
                            <BaseIcon name="lock" class="mt-1 text-kapur-500" />
                            <div class="min-w-0">
                                <h2 class="text-h3 font-semibold text-ink">{{ quiz.gate.reason }}</h2>
                                <ul v-if="quiz.gate.pending?.length" class="mt-3 space-y-1">
                                    <li v-for="item in quiz.gate.pending" :key="item.id" class="text-small text-kapur-600">
                                        {{ item.concept }}:
                                        <RouterLink v-if="item.lesson?.id" :to="`/courses/${courseId}/lessons/${item.lesson.id}`" class="font-semibold text-samudra-600 underline">{{ item.lesson.title }}</RouterLink>
                                        <template v-else>{{ item.lesson?.title }}</template>
                                    </li>
                                </ul>
                                <BaseButton v-if="quiz.gate.pending?.length" class="mt-4" variant="secondary" :to="`/courses/${courseId}/remediation`">Buka Remedial Saya</BaseButton>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Riwayat percobaan -->
                <div>
                    <h2 class="font-serif text-h2 font-medium text-ink">Riwayat percobaan</h2>
                    <p v-if="quiz.attempts.length === 0" class="mt-3 text-body text-kapur-600">Belum ada percobaan.</p>
                    <ul v-else class="mt-4 border-t border-line">
                        <li v-for="attempt in quiz.attempts" :key="attempt.id" class="flex min-h-14 flex-wrap items-center gap-x-6 gap-y-1 border-b border-line py-3">
                            <span class="w-20 text-small font-semibold text-ink">Ke-{{ attempt.attempt_no }}</span>
                            <span class="min-w-0 flex-1 text-small text-kapur-600">
                                {{ attempt.status === 'submitted' ? `Dikirim ${formatTime(attempt.submitted_at)}` : 'Sedang dikerjakan' }}
                            </span>
                            <span class="w-20 text-right font-serif text-h3 tabular-nums text-ink">{{ formatScore(attempt.score_percent) }}</span>
                            <RouterLink v-if="attempt.status === 'submitted'" :to="`/attempts/${attempt.id}/result`" class="text-small font-semibold text-samudra-600 underline">Lihat hasil</RouterLink>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Jalur Belajar (hanya asesmen ulang) -->
            <aside v-if="steps.length" class="xl:col-span-4 max-xl:order-first">
                <div class="rounded-tile bg-well p-6">
                    <h2 class="font-serif text-h2 font-medium text-ink">Jalur Belajar</h2>
                    <div class="mt-5"><JalurBelajar :steps="steps" /></div>
                </div>
            </aside>
        </div>
    </section>
</template>
