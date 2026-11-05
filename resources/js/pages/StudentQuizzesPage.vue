<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
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
        <p v-if="loading" class="text-slate-600">Memuat kuis...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="quizzes.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            Belum ada kuis yang terbit di kelas ini.
        </div>

        <ul v-else class="space-y-2">
            <li v-for="quiz in quizzes" :key="quiz.id" class="rounded-lg border border-slate-200 bg-white p-4">
                <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                    class="font-semibold text-indigo-700 hover:underline">
                    {{ quiz.title }}
                </RouterLink>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">{{ kindLabel(quiz.kind) }}</span>
                    <span class="text-slate-600">{{ quiz.attempts_used }} dari {{ quiz.max_attempts }} percobaan</span>
                    <span class="rounded px-2 py-0.5 font-medium"
                        :class="quiz.gate.status === 'available' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'">
                        {{ quiz.gate.status === 'available' ? 'Tersedia' : 'Terkunci' }}
                    </span>
                    <span v-if="quiz.gate.status === 'locked'" class="text-slate-600">{{ quiz.gate.reason }}</span>
                </div>
            </li>
        </ul>
    </section>
</template>
