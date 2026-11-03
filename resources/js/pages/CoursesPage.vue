<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import CourseCard from '../components/CourseCard.vue';
import CreateCourseForm from '../components/CreateCourseForm.vue';
import JoinCourseForm from '../components/JoinCourseForm.vue';

const auth = useAuthStore();
const courses = ref([]);
const loading = ref(true);
const error = ref('');
const showCreate = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/api/courses');
        courses.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

function onCreated(course) {
    courses.value = [course, ...courses.value];
    showCreate.value = false;
}

function onJoined(course) {
    if (!courses.value.some((c) => c.id === course.id)) {
        courses.value = [course, ...courses.value];
    }
}

onMounted(load);
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Daftar kelas</h1>
            <button v-if="auth.isTeacher && !showCreate" type="button"
                class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="showCreate = true">
                Buat kelas
            </button>
        </div>

        <CreateCourseForm v-if="auth.isTeacher && showCreate" @created="onCreated" @cancel="showCreate = false" />
        <JoinCourseForm v-if="auth.isStudent" @joined="onJoined" />

        <p v-if="loading" class="text-slate-600">Memuat kelas...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="!courses.length" class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            <template v-if="auth.isTeacher">Belum ada kelas. Klik "Buat kelas" untuk memulai.</template>
            <template v-else>Belum ada kelas. Masukkan kode gabung dari gurumu di atas.</template>
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <CourseCard v-for="course in courses" :key="course.id" :course="course" :is-teacher="auth.isTeacher" />
        </div>
    </section>
</template>