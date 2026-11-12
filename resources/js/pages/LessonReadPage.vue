<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
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
        <RouterLink :to="`/courses/${route.params.courseId}/lessons`" class="text-sm text-brand-700 hover:underline">
            &larr; Daftar materi
        </RouterLink>

        <div v-if="loading" class="mt-3 space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-6 w-1/2" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-2/3" />
            <span class="sr-only">Memuat materi...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-3" title="Materi tidak ditemukan." icon="book-open">
            <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/lessons`">Daftar materi</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-3">
            {{ error }}
            <BaseButton variant="secondary" size="sm" class="ml-2" @click="load">Coba lagi</BaseButton>
        </InlineAlert>

        <BaseCard v-else-if="lesson" class="mt-3">
            <article>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">{{ lesson.title }}</h2>
                    <BaseButton v-if="auth.isTeacher" variant="secondary" size="sm" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`">
                        Ubah
                    </BaseButton>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <Badge :tone="lesson.kind === 'remedial' ? 'warning' : 'neutral'">
                        {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                    </Badge>
                    <Badge v-for="concept in lesson.concepts" :key="concept.id" tone="brand">{{ concept.name }}</Badge>
                    <Badge v-if="auth.isTeacher && !lesson.is_published">Draf</Badge>
                </div>

                <div class="mt-4">
                    <MarkdownView :source="lesson.content" />
                </div>

                <div v-if="!auth.isTeacher" class="mt-6 border-t border-slate-200 pt-4">
                    <InlineAlert v-if="lesson.completed" tone="success">Anda sudah menandai materi ini selesai.</InlineAlert>
                    <BaseButton v-else :loading="completing" @click="complete">
                        {{ completing ? 'Menyimpan...' : 'Tandai selesai' }}
                    </BaseButton>
                </div>
            </article>
        </BaseCard>
    </section>
</template>
