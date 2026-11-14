<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { activeCourse, courseNavItems } from '../composables/useShell';
import ConfirmDialog from './ConfirmDialog.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import CopyButton from './ui/CopyButton.vue';
import Dropdown from './ui/Dropdown.vue';
import EmptyState from './ui/EmptyState.vue';
import InlineAlert from './ui/InlineAlert.vue';
import PageHeader from './ui/PageHeader.vue';
import Skeleton from './ui/Skeleton.vue';
import TabNav from './ui/TabNav.vue';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const course = ref(null);
// Dipakai halaman anak (forum) untuk mengetahui kelas arsip tanpa permintaan tambahan.
provide('course', course);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const confirmArchive = ref(false);
const archiving = ref(false);

const ALSO = {
    lessons: ['lesson-new', 'lesson-edit', 'lesson-read'],
    quizzes: ['quiz-new', 'quiz-builder', 'quiz-comparison'],
    students: ['student-detail'],
    forum: ['forum-thread'],
};
const tabs = computed(() =>
    courseNavItems(course.value, auth.user?.role).map((item) => ({ ...item, alsoActive: ALSO[item.key] })),
);
const manageItems = [{ key: 'archive', label: 'Arsipkan kelas', icon: 'archive' }];

// Sidebar (AppShell) membaca kelas aktif dari keadaan bersama.
watch(course, (value) => {
    activeCourse.value = value;
}, { immediate: true });
onBeforeUnmount(() => {
    activeCourse.value = null;
});

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
        <div v-if="loading" class="space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-8 w-2/3" />
            <Skeleton class="h-4 w-1/2" />
            <Skeleton class="h-10 w-full" />
            <span class="sr-only">Memuat kelas...</span>
        </div>

        <EmptyState v-else-if="notFound" as="h1" title="Kelas tidak ditemukan" icon="alert-triangle">
            <BaseButton variant="secondary" :to="{ name: 'courses' }">Kembali ke daftar kelas</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" title="Kelas tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <template v-else-if="course">
            <PageHeader
                :title="course.title"
                :description="course.description ?? ''"
                :breadcrumbs="[{ label: 'Kelas', to: { name: 'courses' } }, { label: course.title }]"
            >
                <template #meta>
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-small text-kapur-600">
                        <Badge v-if="course.is_archived">Arsip</Badge>
                        <span v-if="auth.isTeacher" class="flex flex-wrap items-center gap-2 md:hidden">
                            Kode gabung:
                            <code class="rounded-code bg-kapur-50 px-2 py-1 font-mono font-medium tracking-[0.08em] text-ink">{{ course.join_code }}</code>
                            <CopyButton :value="course.join_code" />
                        </span>
                        <span v-else>Guru: {{ course.teacher_name }}</span>
                    </div>
                </template>
                <template v-if="auth.isTeacher && !course.is_archived" #actions>
                    <span class="print:hidden"><Dropdown label="Kelola kelas" icon="ellipsis" :items="manageItems" @select="confirmArchive = true" /></span>
                </template>
            </PageHeader>

            <TabNav class="mt-6 lg:hidden print:hidden" :tabs="tabs" label="Tab kelas" />

            <div class="mt-8 lg:mt-10">
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