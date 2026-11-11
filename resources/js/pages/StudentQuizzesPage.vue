<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';

const route = useRoute();

const quizzes = ref([]);
const loading = ref(true);
const error = ref('');

function kindLabel(kind) {
    return kind === 'reassessment' ? 'Asesmen ulang' : 'Asesmen';
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
            description="Belum ada kuis yang terbit di kelas ini."
            icon="clipboard"
        />

        <ul v-else class="space-y-2">
            <li v-for="quiz in quizzes" :key="quiz.id">
                <BaseCard>
                    <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                        class="font-semibold text-brand-700 hover:underline">
                        {{ quiz.title }}
                    </RouterLink>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge>{{ kindLabel(quiz.kind) }}</Badge>
                        <span class="text-xs text-slate-600">{{ quiz.attempts_used }} dari {{ quiz.max_attempts }} percobaan</span>
                        <Badge :tone="quiz.gate.status === 'available' ? 'success' : 'warning'">
                            {{ quiz.gate.status === 'available' ? 'Tersedia' : 'Terkunci' }}
                        </Badge>
                        <span v-if="quiz.gate.status === 'locked'" class="text-xs text-slate-600">{{ quiz.gate.reason }}</span>
                        <RouterLink v-if="quiz.gate.pending?.length" :to="`/courses/${route.params.courseId}/remediation`"
                            class="text-xs font-medium text-brand-700 underline">
                            {{ quiz.gate.pending.length }} remedial belum selesai
                        </RouterLink>
                    </div>
                </BaseCard>
            </li>
        </ul>
    </section>
</template>