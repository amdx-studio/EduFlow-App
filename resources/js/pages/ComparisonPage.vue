<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ComparisonTable from '../components/ComparisonTable.vue';
import PrintButton from '../components/PrintButton.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
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

const counts = computed(() => {
    const list = result.value?.concepts ?? [];
    return {
        improved: list.filter((c) => c.trend === 'improved').length,
        unchanged: list.filter((c) => c.trend === 'unchanged').length,
        declined: list.filter((c) => c.trend === 'declined').length,
    };
});
const crumbs = computed(() => [
    { label: 'Kuis', to: `/courses/${route.params.courseId}/quizzes` },
    { label: 'Detail kuis', to: `/courses/${route.params.courseId}/quizzes/${route.params.quizId}` },
    { label: 'Perbandingan' },
]);
</script>

<template>
    <section>
        <PageHeader title="Perbandingan hasil" :description="auth.isTeacher ? 'Perubahan penguasaan siswa sebelum dan sesudah remedial.' : 'Perubahan penguasaan Anda sebelum dan sesudah remedial.'" :breadcrumbs="crumbs">
            <template #actions>
                <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/quizzes/${route.params.quizId}`" class="print:hidden">Kembali ke kuis</BaseButton>
                <PrintButton v-if="result" />
            </template>
        </PageHeader>

        <!-- BR-017: catatan permanen tepat di bawah header -->
        <InlineAlert v-if="result" class="mt-6" tone="info">{{ result.note || NOTE_FALLBACK }}</InlineAlert>

        <div v-if="loading" class="mt-8 space-y-6" role="status" aria-busy="true">
            <div class="grid grid-cols-3 gap-6"><Skeleton class="h-14" /><Skeleton class="h-14" /><Skeleton class="h-14" /></div>
            <Skeleton v-for="n in 5" :key="n" class="h-14 w-full" />
            <span class="sr-only">Memuat perbandingan...</span>
        </div>

        <InlineAlert v-else-if="needsStudent" class="mt-8" tone="info" title="Pilih siswa terlebih dahulu">
            Buka perbandingan lewat halaman detail siswa (tab Siswa, pilih nama siswa);
            atau tambahkan <code class="rounded-code bg-kapur-100 px-1 font-mono">?student_id=</code> dan nomor siswa pada alamat halaman ini.
        </InlineAlert>

        <EmptyState v-else-if="notFound" class="mt-8" title="Perbandingan tidak ditemukan" description="Perbandingan hanya tersedia untuk kuis asesmen ulang." icon="alert-triangle">
            <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/quizzes`">Daftar kuis</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" class="mt-8" tone="danger" title="Perbandingan tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <div v-else-if="result" class="mt-8 space-y-10">
            <EmptyState v-if="result.status === 'incomplete'" title="Belum ada hasil asesmen ulang" description="Perbandingan muncul setelah asesmen ulang dikirim." icon="clipboard" />

            <template v-else>
                <p v-if="result.concepts.length === 0" class="text-body text-kapur-600">
                    Tidak ada konsep yang dapat dibandingkan. Konsep dengan data kurang pada salah satu kuis dikecualikan.
                </p>
                <template v-else>
                    <dl class="grid grid-cols-3 gap-6" aria-label="Ringkasan perubahan">
                        <div>
                            <dd class="font-serif text-[40px] leading-11 font-medium tabular-nums text-samudra-700">{{ counts.improved }}</dd>
                            <dt class="text-small text-kapur-600">Meningkat</dt>
                        </div>
                        <div>
                            <dd class="font-serif text-[40px] leading-11 font-medium tabular-nums text-ink">{{ counts.unchanged }}</dd>
                            <dt class="text-small text-kapur-600">Tetap</dt>
                        </div>
                        <div>
                            <dd class="font-serif text-[40px] leading-11 font-medium tabular-nums text-level-not-mastered-text">{{ counts.declined }}</dd>
                            <dt class="text-small text-kapur-600">Menurun</dt>
                        </div>
                    </dl>
                    <ComparisonTable :concepts="result.concepts" />
                </template>
            </template>
        </div>
    </section>
</template>
