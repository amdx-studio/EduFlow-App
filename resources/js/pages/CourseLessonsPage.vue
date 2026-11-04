<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const lessons = ref([]);
const loading = ref(true);
const error = ref('');

const deleting = ref(null);
const deleteBusy = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/lessons`);
        lessons.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function remove() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/lessons/${deleting.value.id}`);
        lessons.value = lessons.value.filter((l) => l.id !== deleting.value.id);
        toast.success('Materi dihapus.');
    } catch (e) {
        // 409 bila materi sudah dipakai sebagai remedial: pesan datang dari API.
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
        <div v-if="auth.isTeacher" class="mb-4">
            <RouterLink :to="`/courses/${route.params.courseId}/lessons/new`"
                class="inline-flex min-h-10 items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Tambah materi
            </RouterLink>
        </div>
        <p v-if="loading" class="text-slate-600">Memuat materi...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="lessons.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            {{ auth.isTeacher ? 'Belum ada materi. Materi yang Anda buat akan muncul di sini.' : 'Belum ada materi yang terbit di kelas ini.' }}
        </div>

        <ul v-else class="space-y-2">
            <li v-for="lesson in lessons" :key="lesson.id" class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <RouterLink :to="`/courses/${route.params.courseId}/lessons/${lesson.id}`"
                            class="font-semibold text-indigo-700 hover:underline">
                            {{ lesson.title }}
                        </RouterLink>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded px-2 py-0.5 font-medium"
                                :class="lesson.kind === 'remedial' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'">
                                {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                            </span>
                            <span v-for="concept in lesson.concepts" :key="concept.id"
                                class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-800">
                                {{ concept.name }}
                            </span>
                            <span v-if="auth.isTeacher" class="rounded px-2 py-0.5 font-medium"
                                :class="lesson.is_published ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'">
                                {{ lesson.is_published ? 'Terbit' : 'Draf' }}
                            </span>
                            <span v-if="!auth.isTeacher && lesson.completed"
                                class="rounded bg-green-100 px-2 py-0.5 font-medium text-green-800">
                                &#10003; Selesai
                            </span>
                        </div>
                    </div>
                    <RouterLink v-if="auth.isTeacher" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`"
                        class="ml-auto inline-flex min-h-10 items-center rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100">
                        Ubah
                    </RouterLink>
                    <button v-if="auth.isTeacher" type="button"
                        class="min-h-10 rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
                        @click="deleting = lesson">
                        Hapus
                    </button>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus materi?"
            :message="deleting ? `Materi ${deleting.title} akan dihapus. Materi yang sudah dipakai sebagai remedial tidak dapat dihapus.` : ''"
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>
