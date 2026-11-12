<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ComparisonTable from '../components/ComparisonTable.vue';
import PrintButton from '../components/PrintButton.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';

const NOTE_FALLBACK = 'Perubahan skor, bukan bukti sebab-akibat.';

const route = useRoute();
const auth = useAuthStore();

const result = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const studentId = computed(() => route.query.student_id ?? null);
const needsStudent = computed(() => auth.isTeacher && !studentId.value);

async function load() {
    error.value = '';
    notFound.value = false;
    result.value = null;

    if (needsStudent.value) {
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        const params = auth.isTeacher ? { student_id: studentId.value } : {};
        const { data } = await http.get(`/api/quizzes/${route.params.quizId}/comparison`, { params });
        result.value = data.data;
    } catch (e) {
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => [route.params.quizId, route.query.student_id], load);
</script>

<template>
    <section>
        <BaseButton variant="ghost" size="sm" :to="`/courses/${route.params.courseId}/quizzes/${route.params.quizId}`">
            <BaseIcon name="arrow-left" size="sm" />Kembali ke kuis
        </BaseButton>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-semibold">Perbandingan sebelum dan sesudah</h1>
            <PrintButton v-if="result" />
        </div>

        <div v-if="loading" class="mt-3 space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-40 w-full" />
            <span class="sr-only">Memuat perbandingan...</span>
        </div>

        <InlineAlert v-else-if="needsStudent" class="mt-3" tone="info" title="Pilih siswa terlebih dahulu">
            Buka perbandingan lewat halaman detail siswa (tab Siswa, pilih nama siswa);
            atau tambahkan <code class="rounded bg-slate-100 px-1">?student_id=</code> dan nomor siswa pada alamat halaman ini.
        </InlineAlert>

        <div v-else-if="notFound" class="mt-3">
            <EmptyState title="Perbandingan tidak ditemukan" icon="alert-triangle" />
        </div>

        <InlineAlert v-else-if="error" class="mt-3" tone="danger" title="Perbandingan tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="result" class="mt-3 space-y-4">
            <EmptyState v-if="result.status === 'incomplete'" title="Belum ada hasil asesmen ulang" icon="clipboard" />

            <template v-else>
                <p v-if="result.concepts.length === 0" class="text-sm text-slate-600">
                    Tidak ada konsep yang dapat dibandingkan. Konsep dengan data kurang pada salah satu kuis dikecualikan.
                </p>
                <ComparisonTable v-else :concepts="result.concepts" />
            </template>

            <InlineAlert tone="info">{{ result.note || NOTE_FALLBACK }}</InlineAlert>
        </div>
    </section>
</template>