<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const quizzes = ref([]);
const loading = ref(true);
const error = ref('');

const deleting = ref(null);
const deleteBusy = ref(false);

function kindLabel(kind) {
    return kind === 'reassessment' ? 'Asesmen ulang' : 'Asesmen';
}

function baseTitle(id) {
    return quizzes.value.find((q) => q.id === id)?.title ?? `#${id}`;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/quizzes`);
        quizzes.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function remove() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/quizzes/${deleting.value.id}`);
        quizzes.value = quizzes.value.filter((q) => q.id !== deleting.value.id);
        toast.success('Kuis dihapus.');
    } catch (e) {
        // 409 bila kuis sudah terbit atau sudah ada percobaan: pesan datang dari API.
        toast.error(errorMessage(e));
    } finally {
        deleteBusy.value = false;
        deleting.value = null;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <section>
        <div class="mb-4">
            <RouterLink :to="`/courses/${route.params.courseId}/quizzes/new`"
                class="inline-flex min-h-10 items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Buat kuis
            </RouterLink>
        </div>

        <p v-if="loading" class="text-slate-600">Memuat kuis...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="quizzes.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            Belum ada kuis. Buat kuis pertama, lalu tambahkan soal yang bertaut ke konsep.
        </div>

        <ul v-else class="space-y-2">
            <li v-for="quiz in quizzes" :key="quiz.id" class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                            class="font-semibold text-indigo-700 hover:underline">
                            {{ quiz.title }}
                        </RouterLink>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded px-2 py-0.5 font-medium"
                                :class="quiz.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'">
                                {{ quiz.status === 'published' ? 'Terbit' : 'Draf' }}
                            </span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">{{ kindLabel(quiz.kind) }}</span>
                            <span v-if="quiz.kind === 'reassessment'" class="text-slate-600">dasar: {{ baseTitle(quiz.base_quiz_id) }}</span>
                            <span class="text-slate-600">{{ quiz.questions_count }} soal</span>
                            <span class="text-slate-600">{{ quiz.attempts_count }} percobaan</span>
                            <span class="text-slate-600">maks. {{ quiz.max_attempts }}x</span>
                        </div>
                    </div>
                    <button v-if="quiz.status === 'draft'" type="button"
                        class="min-h-10 rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
                        @click="deleting = quiz">
                        Hapus
                    </button>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus kuis?"
            :message="deleting ? `Kuis ${deleting.title} akan dihapus. Hanya kuis draf tanpa percobaan yang dapat dihapus.` : ''"
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>
