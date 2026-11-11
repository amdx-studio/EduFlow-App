<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import DataTable from '../components/ui/DataTable.vue';
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
        <BaseButton variant="ghost" size="sm" :to="`/courses/${courseId}/quizzes`">
            <BaseIcon name="arrow-left" size="sm" />Daftar kuis
        </BaseButton>

        <div v-if="loading" class="mt-3 space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-28 w-full" />
            <Skeleton class="h-32 w-full" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <div v-else-if="notFound" class="mt-3">
            <EmptyState title="Kuis tidak ditemukan" icon="alert-triangle" />
        </div>

        <InlineAlert v-else-if="error" class="mt-3" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="quiz" class="mt-3 space-y-4">
            <BaseCard>
                <h2 class="text-lg font-semibold">{{ quiz.title }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <Badge>{{ kindLabel(quiz.kind) }}</Badge>
                    <span>Percobaan terpakai: {{ quiz.attempts_used }} dari {{ quiz.max_attempts }}</span>
                    <RouterLink v-if="quiz.kind === 'reassessment'" :to="`/courses/${courseId}/quizzes/${quizId}/comparison`"
                        class="font-medium text-brand-700 underline">
                        Lihat perbandingan
                    </RouterLink>
                </div>

                <div v-if="quiz.gate.status === 'available'" class="mt-4">
                    <BaseButton :loading="starting" @click="start">
                        {{ starting ? 'Memuat...' : (activeAttempt ? 'Lanjutkan kuis' : 'Mulai kuis') }}
                    </BaseButton>
                    <p v-if="activeAttempt" class="mt-2 text-sm text-slate-600">
                        Anda memiliki percobaan ke-{{ activeAttempt.attempt_no }} yang belum dikirim.
                    </p>
                </div>

                <InlineAlert v-else class="mt-4" tone="warning" title="Kuis terkunci">
                    <p>{{ quiz.gate.reason }}</p>
                    <ul v-if="quiz.gate.pending?.length" class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="item in quiz.gate.pending" :key="item.id">
                            {{ item.concept }}: {{ item.lesson.title }}
                        </li>
                    </ul>
                    <RouterLink v-if="quiz.gate.pending?.length" :to="`/courses/${courseId}/remediation`"
                        class="mt-2 inline-block font-medium text-brand-700 underline">
                        Buka Remedial Saya
                    </RouterLink>
                </InlineAlert>
            </BaseCard>

            <div>
                <h3 class="font-semibold">Riwayat percobaan</h3>
                <p v-if="quiz.attempts.length === 0" class="mt-2 text-sm text-slate-600">Belum ada percobaan.</p>
                <div v-else class="mt-2">
                    <DataTable label="Riwayat percobaan" caption="Riwayat percobaan kuis ini">
                        <thead>
                            <tr>
                                <th scope="col">Percobaan</th>
                                <th scope="col">Status</th>
                                <th scope="col">Skor</th>
                                <th scope="col">Waktu kirim</th>
                                <th scope="col">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attempt in quiz.attempts" :key="attempt.id">
                                <td class="whitespace-nowrap">Ke-{{ attempt.attempt_no }}</td>
                                <td class="whitespace-nowrap">{{ attempt.status === 'submitted' ? 'Terkirim' : 'Sedang dikerjakan' }}</td>
                                <td class="tabular-nums">{{ formatScore(attempt.score_percent) }}</td>
                                <td class="whitespace-nowrap">{{ formatTime(attempt.submitted_at) }}</td>
                                <td>
                                    <RouterLink v-if="attempt.status === 'submitted'" :to="`/attempts/${attempt.id}/result`"
                                        class="font-medium text-brand-700 underline">Lihat hasil</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </div>
        </div>
    </section>
</template>