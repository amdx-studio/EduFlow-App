<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ComparisonTable from '../components/ComparisonTable.vue';
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
        <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${route.params.quizId}`"
            class="text-sm text-indigo-700 hover:underline">
            &larr; Kembali ke kuis
        </RouterLink>

        <h1 class="mt-2 text-lg font-bold">Perbandingan sebelum dan sesudah</h1>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat perbandingan...</p>

        <div v-else-if="needsStudent" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-slate-600">
            Pilih siswa untuk melihat perbandingannya. Pintu masuk dari halaman detail siswa tersedia di fase berikutnya;
            sementara itu tambahkan <code class="rounded bg-slate-100 px-1">?student_id=</code> dan nomor siswa pada alamat halaman ini.
        </div>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Perbandingan tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="result" class="mt-3 space-y-4">
            <div v-if="result.status === 'incomplete'"
                class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
                Belum ada hasil asesmen ulang.
            </div>

            <div v-else class="rounded-lg border border-slate-200 bg-white p-5">
                <p v-if="result.concepts.length === 0" class="text-sm text-slate-600">
                    Tidak ada konsep yang dapat dibandingkan. Konsep dengan data kurang pada salah satu kuis dikecualikan.
                </p>
                <ComparisonTable v-else :concepts="result.concepts" />
            </div>

            <p class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700" role="note">
                {{ result.note || NOTE_FALLBACK }}
            </p>
        </div>
    </section>
</template>
