<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import CourseCard from '../components/CourseCard.vue';
import CreateCourseForm from '../components/CreateCourseForm.vue';
import JoinCourseForm from '../components/JoinCourseForm.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
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
    <section class="space-y-4">
        <PageHeader
            title="Daftar kelas"
            :description="auth.isTeacher ? 'Kelola kelas, materi, dan kuis Anda.' : 'Kelas yang Anda ikuti.'"
        >
            <template v-if="auth.isTeacher && !showCreate" #actions>
                <BaseButton @click="showCreate = true"><BaseIcon name="plus" size="sm" />Buat kelas</BaseButton>
            </template>
        </PageHeader>

        <CreateCourseForm v-if="auth.isTeacher && showCreate" @created="onCreated" @cancel="showCreate = false" />
        <JoinCourseForm v-if="auth.isStudent" @joined="onJoined" />

        <div v-if="loading" class="grid gap-3 sm:grid-cols-2" role="status" aria-busy="true">
            <BaseCard v-for="n in 2" :key="n">
                <div class="space-y-2">
                    <Skeleton class="h-6 w-2/3" />
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="h-4 w-1/2" />
                </div>
            </BaseCard>
            <span class="sr-only">Memuat kelas...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Kelas tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="!courses.length"
            title="Belum ada kelas"
            :description="auth.isTeacher ? 'Klik Buat kelas untuk memulai.' : 'Masukkan kode gabung dari gurumu di atas.'"
            icon="book-open"
        />

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <CourseCard v-for="course in courses" :key="course.id" :course="course" :is-teacher="auth.isTeacher" />
        </div>
    </section>
</template>