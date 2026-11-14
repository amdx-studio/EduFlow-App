<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';

const route = useRoute();

const quizzes = ref([]);
const loading = ref(true);
const error = ref('');

function isReassessment(quiz) {
    return quiz.kind === 'reassessment';
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

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <section>
        <div>
            <h2 class="font-serif text-h2 font-medium text-ink">Kuis</h2>
            <p v-if="!loading && !error" class="text-caption text-kapur-500">{{ quizzes.length }} kuis</p>
        </div>

        <div v-if="loading" class="mt-4 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 4" :key="n" class="flex min-h-18 items-center gap-4 border-b border-line py-4">
                <div class="flex-1 space-y-2"><Skeleton class="h-4 w-1/3" /><Skeleton class="h-3 w-1/4" /></div>
                <Skeleton class="h-9 w-20" />
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
            description="Guru belum menerbitkan kuis di kelas ini. Kuis akan muncul di sini setelah terbit."
            icon="clipboard"
        />

        <ul v-else class="mt-4 border-t border-line">
            <li v-for="quiz in quizzes" :key="quiz.id" class="border-b border-line">
                <div class="flex min-h-18 flex-col gap-3 py-4 md:flex-row md:items-center md:gap-6">
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
                        <p class="mt-1 text-caption text-kapur-500">Percobaan {{ quiz.attempts_used }} dari {{ quiz.max_attempts }}</p>
                    </div>

                    <div v-if="quiz.gate.status === 'available'" class="md:shrink-0">
                        <BaseButton
                            class="max-md:w-full"
                            variant="secondary"
                            size="sm"
                            :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                            :aria-label="`Buka ${quiz.title}`"
                        >Buka</BaseButton>
                    </div>
                    <div v-else class="flex min-w-0 flex-col gap-1 md:max-w-72 md:items-end md:text-right">
                        <p class="inline-flex items-start gap-2 text-small text-kapur-600 md:flex-row-reverse">
                            <BaseIcon name="lock" size="sm" class="mt-0.5 shrink-0 text-kapur-500" />
                            <span>{{ quiz.gate.reason }}</span>
                        </p>
                        <RouterLink
                            v-if="quiz.gate.pending?.length"
                            :to="`/courses/${route.params.courseId}/remediation`"
                            class="text-small font-semibold text-samudra-600 underline"
                        >{{ quiz.gate.pending.length }} remedial belum selesai</RouterLink>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</template>
