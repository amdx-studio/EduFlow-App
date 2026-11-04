<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const lesson = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const completing = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/lessons/${route.params.lessonId}`);
        lesson.value = data.data;
    } catch (e) {
        lesson.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

async function complete() {
    completing.value = true;
    try {
        await http.post(`/api/lessons/${lesson.value.id}/complete`);
        lesson.value = { ...lesson.value, completed: true };
        toast.success('Materi ditandai selesai.');
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        completing.value = false;
    }
}

onMounted(load);
watch(() => route.params.lessonId, load);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${route.params.courseId}/lessons`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar materi
        </RouterLink>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat materi...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Materi tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <article v-else-if="lesson" class="mt-3 rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h2 class="text-xl font-bold">{{ lesson.title }}</h2>
                <RouterLink v-if="auth.isTeacher" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`"
                    class="inline-flex min-h-10 items-center rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100">
                    Ubah
                </RouterLink>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded px-2 py-0.5 font-medium"
                    :class="lesson.kind === 'remedial' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'">
                    {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                </span>
                <span v-for="concept in lesson.concepts" :key="concept.id"
                    class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-800">
                    {{ concept.name }}
                </span>
                <span v-if="auth.isTeacher && !lesson.is_published"
                    class="rounded bg-slate-200 px-2 py-0.5 font-medium text-slate-700">
                    Draf
                </span>
            </div>

            <div class="mt-4">
                <MarkdownView :source="lesson.content" />
            </div>

            <div v-if="!auth.isTeacher" class="mt-6 border-t border-slate-200 pt-4">
                <p v-if="lesson.completed" class="font-medium text-green-800">&#10003; Anda sudah menandai materi ini selesai.</p>
                <button v-else type="button" :disabled="completing"
                    class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @click="complete">
                    {{ completing ? 'Menyimpan...' : 'Tandai selesai' }}
                </button>
            </div>
        </article>
    </section>
</template>
