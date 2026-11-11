<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { RouterLink } from 'vue-router';
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
            <BaseButton :to="`/courses/${route.params.courseId}/lessons/new`">
                <BaseIcon name="plus" size="sm" />Tambah materi
            </BaseButton>
        </div>

        <div v-if="loading" class="space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <span class="sr-only">Memuat materi...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Materi tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="lessons.length === 0"
            title="Belum ada materi"
            :description="auth.isTeacher ? 'Materi yang Anda buat akan muncul di sini.' : 'Belum ada materi yang terbit di kelas ini.'"
            icon="book-open"
        />

        <ul v-else class="space-y-2">
            <li v-for="lesson in lessons" :key="lesson.id">
                <BaseCard>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/courses/${route.params.courseId}/lessons/${lesson.id}`"
                                class="font-semibold text-brand-700 hover:underline">
                                {{ lesson.title }}
                            </RouterLink>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge :tone="lesson.kind === 'remedial' ? 'warning' : 'neutral'">
                                    {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                                </Badge>
                                <Badge v-for="concept in lesson.concepts" :key="concept.id" tone="brand">{{ concept.name }}</Badge>
                                <Badge v-if="auth.isTeacher" :tone="lesson.is_published ? 'success' : 'neutral'">
                                    {{ lesson.is_published ? 'Terbit' : 'Draf' }}
                                </Badge>
                                <Badge v-if="!auth.isTeacher && lesson.completed" tone="success">
                                    <BaseIcon name="check" size="sm" class="mr-1" />Selesai
                                </Badge>
                            </div>
                        </div>
                        <div v-if="auth.isTeacher" class="flex gap-2">
                            <BaseButton variant="secondary" size="sm" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`">
                                <BaseIcon name="edit" size="sm" />Ubah
                            </BaseButton>
                            <BaseButton variant="danger-outline" size="sm" @click="deleting = lesson">
                                <BaseIcon name="trash" size="sm" />Hapus
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
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