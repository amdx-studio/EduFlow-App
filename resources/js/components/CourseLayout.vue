<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import ConfirmDialog from './ConfirmDialog.vue';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const course = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const confirmArchive = ref(false);
const archiving = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}`);
        course.value = data.data;
    } catch (e) {
        course.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

async function archive() {
    archiving.value = true;
    try {
        const { data } = await http.post(`/api/courses/${course.value.id}/archive`);
        course.value = data.data;
        toast.success('Kelas diarsipkan.');
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        archiving.value = false;
        confirmArchive.value = false;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <section>
        <p v-if="loading" class="text-slate-600">Memuat kelas...</p>

        <div v-else-if="notFound" class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Kelas tidak ditemukan.</p>
            <RouterLink to="/courses" class="mt-2 inline-block text-indigo-700 underline">Kembali ke daftar kelas</RouterLink>
        </div>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <template v-else-if="course">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <RouterLink to="/courses" class="text-sm text-indigo-700 hover:underline">&larr; Daftar kelas</RouterLink>
                    <h1 class="mt-1 text-xl font-bold">
                        {{ course.title }}
                        <span v-if="course.is_archived" class="ml-2 rounded bg-slate-200 px-2 py-0.5 align-middle text-xs font-normal text-slate-700">Arsip</span>
                    </h1>
                    <p v-if="course.description" class="mt-1 text-sm text-slate-600">{{ course.description }}</p>
                    <p v-if="auth.isTeacher" class="mt-2 text-sm text-slate-600">
                        Kode gabung:
                        <code class="rounded bg-slate-100 px-2 py-1 font-mono font-semibold tracking-wider">{{ course.join_code }}</code>
                    </p>
                    <p v-else class="mt-2 text-sm text-slate-600">Guru: {{ course.teacher_name }}</p>
                </div>
                <button v-if="auth.isTeacher && !course.is_archived" type="button"
                    class="min-h-10 rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                    @click="confirmArchive = true">
                    Arsipkan kelas
                </button>
            </div>

            <nav class="mt-4 flex overflow-x-auto border-b border-slate-200 text-sm" aria-label="Tab kelas">
                <RouterLink :to="`/courses/${course.id}/lessons`"
                    class="shrink-0 border-b-2 border-transparent px-4 py-2 text-slate-600 hover:text-indigo-700"
                    active-class="!border-indigo-600 font-semibold !text-indigo-700">
                    Materi
                </RouterLink>
                <RouterLink v-if="auth.isTeacher" :to="`/courses/${course.id}/concepts`"
                    class="shrink-0 border-b-2 border-transparent px-4 py-2 text-slate-600 hover:text-indigo-700"
                    active-class="!border-indigo-600 font-semibold !text-indigo-700">
                    Konsep
                </RouterLink>
                <RouterLink v-if="auth.isTeacher" :to="`/courses/${course.id}/quizzes`"
                    class="shrink-0 border-b-2 border-transparent px-4 py-2 text-slate-600 hover:text-indigo-700"
                    active-class="!border-indigo-600 font-semibold !text-indigo-700">
                    Kuis
                </RouterLink>
                <RouterLink v-if="auth.isTeacher" :to="`/courses/${course.id}/students`"
                    class="shrink-0 border-b-2 border-transparent px-4 py-2 text-slate-600 hover:text-indigo-700"
                    active-class="!border-indigo-600 font-semibold !text-indigo-700">
                    Siswa
                </RouterLink>
            </nav>

            <div class="mt-4">
                <RouterView />
            </div>

            <ConfirmDialog
                :open="confirmArchive"
                title="Arsipkan kelas?"
                message="Kelas arsip tidak menerima siswa baru. Siswa yang sudah terdaftar tetap bisa melihatnya."
                confirm-label="Ya, arsipkan"
                :busy="archiving"
                @confirm="archive"
                @cancel="confirmArchive = false" />
        </template>
    </section>
</template>
