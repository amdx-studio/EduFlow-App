<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const quizzes = ref([]);
const loading = ref(true);
const error = ref('');

const deleting = ref(null);
const deleteBusy = ref(false);

function kindLabel(kind) {
    return kind === 'reassessment' ? 'Asesmen ulang' : 'Asesmen';
}

function baseTitle(id) {
    return quizzes.value.find((q) => q.id === id)?.title ?? `#${id}`;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/quizzes`);
        quizzes.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function remove() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/quizzes/${deleting.value.id}`);
        quizzes.value = quizzes.value.filter((q) => q.id !== deleting.value.id);
        toast.success('Kuis dihapus.');
    } catch (e) {
        // 409 bila kuis sudah terbit atau sudah ada percobaan: pesan datang dari API.
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
        <div class="mb-4">
            <BaseButton :to="`/courses/${route.params.courseId}/quizzes/new`">
                <BaseIcon name="plus" size="sm" />Buat kuis
            </BaseButton>
        </div>

        <div v-if="loading" class="space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="quizzes.length === 0"
            title="Belum ada kuis"
            description="Buat kuis pertama, lalu tambahkan soal yang bertaut ke konsep."
            icon="clipboard"
        />

        <ul v-else class="space-y-2">
            <li v-for="quiz in quizzes" :key="quiz.id">
                <BaseCard>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                                class="font-semibold text-brand-700 hover:underline">
                                {{ quiz.title }}
                            </RouterLink>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge :tone="quiz.status === 'published' ? 'success' : 'neutral'">
                                    {{ quiz.status === 'published' ? 'Terbit' : 'Draf' }}
                                </Badge>
                                <Badge>{{ kindLabel(quiz.kind) }}</Badge>
                                <span v-if="quiz.kind === 'reassessment'" class="text-xs text-slate-600">dasar: {{ baseTitle(quiz.base_quiz_id) }}</span>
                                <span class="text-xs text-slate-600">{{ quiz.questions_count }} soal</span>
                                <span class="text-xs text-slate-600">{{ quiz.attempts_count }} percobaan</span>
                                <span class="text-xs text-slate-600">maks. {{ quiz.max_attempts }}x</span>
                            </div>
                        </div>
                        <BaseButton v-if="quiz.status === 'draft'" variant="danger-outline" size="sm" @click="deleting = quiz">
                            <BaseIcon name="trash" size="sm" />Hapus
                        </BaseButton>
                    </div>
                </BaseCard>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus kuis?"
            :message="deleting ? `Kuis ${deleting.title} akan dihapus. Hanya kuis draf tanpa percobaan yang dapat dihapus.` : ''"
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>