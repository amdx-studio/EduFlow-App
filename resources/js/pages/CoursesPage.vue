<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import CourseCard from '../components/CourseCard.vue';
import CreateCourseForm from '../components/CreateCourseForm.vue';
import JoinCourseForm from '../components/JoinCourseForm.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import Modal from '../components/ui/Modal.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import Skeleton from '../components/ui/Skeleton.vue';

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
    <section>
        <PageHeader
            title="Kelas"
            :description="auth.isTeacher ? 'Kelola kelas, materi, dan kuis Anda.' : 'Kelas yang Anda ikuti.'"
        >
            <template v-if="auth.isTeacher" #actions>
                <BaseButton @click="showCreate = true"><BaseIcon name="plus" size="sm" />Buat kelas</BaseButton>
            </template>
        </PageHeader>

        <JoinCourseForm v-if="auth.isStudent" class="mt-8" @joined="onJoined" />

        <h2 class="mt-10 font-serif text-h2 font-medium text-ink">Kelas Anda</h2>

        <div v-if="loading" class="mt-4 grid gap-4 md:grid-cols-2" role="status" aria-busy="true">
            <div v-for="n in 2" :key="n" class="rounded-tile bg-well p-6">
                <Skeleton class="h-12 w-12" />
                <Skeleton class="mt-4 h-6 w-2/3" />
                <Skeleton class="mt-3 h-4 w-full" />
                <Skeleton class="mt-2 h-4 w-1/2" />
            </div>
            <span class="sr-only">Memuat kelas...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-4" tone="danger" title="Kelas tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="!courses.length"
            class="mt-4"
            :title="auth.isTeacher ? 'Belum ada kelas' : 'Belum ada kelas yang diikuti'"
            :description="auth.isTeacher ? 'Buat kelas pertama untuk mulai menambahkan konsep, materi, dan kuis.' : 'Masukkan kode gabung dari guru Anda untuk bergabung.'"
            icon="book-open"
        >
            <BaseButton v-if="auth.isTeacher" @click="showCreate = true">Buat kelas</BaseButton>
        </EmptyState>

        <div v-else class="mt-4 grid gap-4 md:grid-cols-2">
            <CourseCard v-for="course in courses" :key="course.id" :course="course" :is-teacher="auth.isTeacher" />
        </div>

        <Modal v-model="showCreate" title="Buat kelas" size="md">
            <CreateCourseForm @created="onCreated" @cancel="showCreate = false" />
        </Modal>
    </section>
</template>
