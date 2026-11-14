<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import Dropdown from '../components/ui/Dropdown.vue';
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

const rowItems = [{ key: 'delete', label: 'Hapus kuis', icon: 'trash', danger: true }];

function isReassessment(quiz) {
    return quiz.kind === 'reassessment';
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
        // 409 bila kuis sudah terbit, sudah ada percobaan, atau dipakai asesmen ulang: pesan datang dari API.
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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-h2 font-medium text-ink">Kuis</h2>
                <p v-if="!loading && !error" class="text-caption text-kapur-500">{{ quizzes.length }} kuis</p>
            </div>
            <BaseButton class="max-md:w-full" :to="`/courses/${route.params.courseId}/quizzes/new`">
                <BaseIcon name="plus" size="sm" />Buat kuis
            </BaseButton>
        </div>

        <div v-if="loading" class="mt-4 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 4" :key="n" class="flex min-h-18 items-center gap-4 border-b border-line py-4">
                <div class="flex-1 space-y-2"><Skeleton class="h-4 w-1/3" /><Skeleton class="h-3 w-1/2" /></div>
                <Skeleton class="h-7 w-16 !rounded-full" />
            </div>
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-4" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="quizzes.length === 0"
            class="mt-4"
            title="Belum ada kuis"
            description="Buat kuis, lalu tautkan tiap soal ke satu konsep."
            icon="clipboard"
        >
            <BaseButton :to="`/courses/${route.params.courseId}/quizzes/new`">
                <BaseIcon name="plus" size="sm" />Buat kuis
            </BaseButton>
        </EmptyState>

        <ul v-else class="mt-4 border-t border-line">
            <li v-for="quiz in quizzes" :key="quiz.id" class="border-b border-line">
                <div class="flex min-h-18 items-center gap-2 py-4">
                    <div class="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center md:gap-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <RouterLink
                                    :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                                    class="min-w-0 truncate text-h3 font-semibold text-samudra-600 hover:underline"
                                >{{ quiz.title }}</RouterLink>
                                <Badge :tone="isReassessment(quiz) ? 'remedial' : 'neutral'">
                                    {{ isReassessment(quiz) ? 'Asesmen ulang' : 'Kuis dasar' }}
                                </Badge>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-5 gap-y-1 text-caption text-kapur-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <BaseIcon name="clipboard" size="sm" />{{ quiz.questions_count }} soal
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <BaseIcon name="check-circle" size="sm" />{{ quiz.attempts_count }} percobaan
                                </span>
                                <span>Maks. {{ quiz.max_attempts }}x</span>
                                <span v-if="isReassessment(quiz)">Kuis dasar: {{ baseTitle(quiz.base_quiz_id) }}</span>
                            </div>
                        </div>
                        <Badge :tone="quiz.status === 'published' ? 'success' : 'neutral'" class="self-start md:self-auto">
                            {{ quiz.status === 'published' ? 'Terbit' : 'Draf' }}
                        </Badge>
                    </div>
                    <div class="w-11 shrink-0">
                        <Dropdown
                            v-if="quiz.status === 'draft'"
                            icon="ellipsis"
                            icon-only
                            :label="`Tindakan untuk ${quiz.title}`"
                            :items="rowItems"
                            @select="deleting = quiz"
                        />
                    </div>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus kuis?"
            :message="deleting ? `Kuis ${deleting.title} dan semua soalnya akan dihapus. Hanya kuis draf tanpa percobaan yang dapat dihapus.` : ''"
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>
