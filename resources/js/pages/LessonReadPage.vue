<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ConceptChip from '../components/domain/ConceptChip.vue';
import GlyphLevel from '../components/domain/GlyphLevel.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';
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
const completedAt = ref(null);

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/lessons/${route.params.lessonId}`);
        lesson.value = data.data;
        completedAt.value = null;
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
        const { data } = await http.post(`/api/lessons/${lesson.value.id}/complete`);
        lesson.value = { ...lesson.value, completed: true };
        completedAt.value = data?.data?.completed_at ?? null;
        const updated = data?.data?.assignments_completed ?? 0;
        toast.success(updated > 0 ? `Materi ditandai selesai. ${updated} tugas remedial diperbarui.` : 'Materi ditandai selesai.');
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
    <section class="mx-auto max-w-170">
        <RouterLink :to="`/courses/${route.params.courseId}/lessons`" class="inline-flex items-center gap-1 text-small font-semibold text-samudra-600 hover:underline">
            <BaseIcon name="arrow-left" size="sm" />Materi
        </RouterLink>

        <div v-if="loading" class="mt-6 space-y-4" role="status" aria-busy="true">
            <Skeleton class="h-5 w-24" />
            <Skeleton class="h-10 w-3/4" />
            <Skeleton class="h-5 w-full" />
            <Skeleton class="h-5 w-full" />
            <Skeleton class="h-5 w-2/3" />
            <span class="sr-only">Memuat materi...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-6" title="Materi tidak ditemukan." description="Materi ini mungkin sudah dihapus atau belum diterbitkan." icon="book-open">
            <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/lessons`">Daftar materi</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-6" title="Materi tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <article v-else-if="lesson" class="mt-6">
            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="lesson.kind === 'remedial' ? 'remedial' : 'neutral'">
                    {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                </Badge>
                <ConceptChip v-for="concept in lesson.concepts" :key="concept.id" :name="concept.name" />
                <Badge v-if="auth.isTeacher && !lesson.is_published">Draf</Badge>
            </div>

            <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
                <h2 class="font-serif text-h1 font-medium text-ink">{{ lesson.title }}</h2>
                <BaseButton v-if="auth.isTeacher" variant="secondary" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`">
                    <BaseIcon name="edit" size="sm" />Ubah materi
                </BaseButton>
            </div>

            <div class="mt-8">
                <MarkdownView :source="lesson.content" />
            </div>

            <div v-if="!auth.isTeacher" class="mt-12 rounded-tile bg-well p-5 max-md:sticky max-md:bottom-0 md:p-6">
                <div v-if="lesson.completed" class="flex items-center gap-3" role="status">
                    <GlyphLevel level="mastered" :size="28" />
                    <div>
                        <p class="text-h3 font-semibold text-ink">Selesai</p>
                        <p v-if="completedAt" class="text-caption text-kapur-600">{{ formatDateTime(completedAt) }} WIB</p>
                    </div>
                </div>
                <div v-else class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-h3 font-semibold text-ink">Sudah selesai membaca?</p>
                    <BaseButton size="lg" :loading="completing" @click="complete">
                        {{ completing ? 'Menyimpan...' : 'Tandai selesai' }}
                    </BaseButton>
                </div>
            </div>
        </article>
    </section>
</template>
