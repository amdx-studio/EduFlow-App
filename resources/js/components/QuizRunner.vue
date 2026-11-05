<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';
import ConfirmDialog from './ConfirmDialog.vue';

const props = defineProps({
    attemptId: { type: [String, Number], required: true },
});

const router = useRouter();
const toast = useToastStore();

const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const quiz = ref(null);
const attemptNo = ref(null);
const questions = ref([]);
const answers = ref({}); // question_id -> option_id (tanpa kunci jawaban)
const current = ref(0);

const saving = ref(false);
const saveState = ref('idle'); // idle | saving | saved | error
const confirming = ref(false);
const submitting = ref(false);

const question = computed(() => questions.value[current.value] ?? null);
const unanswered = computed(() => questions.value.filter((q) => answers.value[q.id] == null).length);

const saveLabel = computed(() => ({
    idle: '',
    saving: 'Menyimpan...',
    saved: 'Tersimpan',
    error: 'Gagal menyimpan',
}[saveState.value]));

function goToResult(replace) {
    const target = `/attempts/${props.attemptId}/result`;
    return replace ? router.replace(target) : router.push(target);
}

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const { data } = await http.get(`/api/attempts/${props.attemptId}`);
        const payload = data.data;

        const quizRes = await http.get(`/api/quizzes/${payload.attempt.quiz_id}`);
        quiz.value = quizRes.data.data;

        // Percobaan yang sudah terkirim mengembalikan payload hasil (tanpa status berjalan).
        if (payload.attempt.status !== 'in_progress') {
            await goToResult(true);
            return;
        }

        attemptNo.value = payload.attempt.attempt_no;
        questions.value = payload.questions;
        answers.value = Object.fromEntries(payload.answers.map((a) => [a.question_id, a.option_id]));
        current.value = 0;
    } catch (e) {
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

async function save(questionId, optionId) {
    const previous = answers.value[questionId] ?? null;
    answers.value = { ...answers.value, [questionId]: optionId };
    saving.value = true;
    saveState.value = 'saving';
    try {
        await http.put(`/api/attempts/${props.attemptId}/answers`, {
            question_id: questionId,
            option_id: optionId,
        });
        saveState.value = 'saved';
    } catch (e) {
        answers.value = { ...answers.value, [questionId]: previous };
        saveState.value = 'error';
        toast.error(errorMessage(e));
        if (e.response?.status === 409) {
            await goToResult(true);
        }
    } finally {
        saving.value = false;
    }
}

function choose(optionId) {
    return save(question.value.id, optionId);
}

function clearAnswer() {
    return save(question.value.id, null);
}

async function submit() {
    submitting.value = true;
    try {
        await http.post(`/api/attempts/${props.attemptId}/submit`);
        toast.success('Kuis dikirim.');
        await goToResult(false);
    } catch (e) {
        toast.error(errorMessage(e));
        if (e.response?.status === 409) {
            await goToResult(true);
        }
    } finally {
        submitting.value = false;
        confirming.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section>
        <p v-if="loading" class="text-slate-600">Memuat kuis...</p>

        <div v-else-if="notFound" class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Percobaan tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="question" class="space-y-4">
            <div>
                <h1 class="text-lg font-bold">{{ quiz.title }}</h1>
                <p class="text-sm text-slate-600">Percobaan ke-{{ attemptNo }}</p>
            </div>

            <nav class="flex flex-wrap gap-2" aria-label="Nomor soal">
                <button v-for="(q, i) in questions" :key="q.id" type="button"
                    class="min-h-10 min-w-10 rounded-md border px-3 py-2 text-sm"
                    :class="i === current
                        ? 'border-indigo-600 bg-indigo-600 font-semibold text-white'
                        : 'border-slate-300 bg-white hover:bg-slate-100'"
                    :aria-current="i === current ? 'step' : undefined"
                    :aria-label="`Soal ${i + 1}, ${answers[q.id] != null ? 'sudah dijawab' : 'belum dijawab'}`"
                    @click="current = i">
                    {{ i + 1 }}<span v-if="answers[q.id] != null" aria-hidden="true"> &#10003;</span>
                </button>
            </nav>

            <article class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">Soal {{ current + 1 }} dari {{ questions.length }} &middot; {{ question.points }} poin</p>
                <p class="mt-2 whitespace-pre-line font-medium">{{ question.body }}</p>

                <fieldset class="mt-4">
                    <legend class="sr-only">Pilihan jawaban</legend>
                    <div class="space-y-2">
                        <label v-for="option in question.options" :key="option.id"
                            class="flex min-h-10 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm"
                            :class="answers[question.id] === option.id ? 'border-indigo-600 bg-indigo-50' : 'border-slate-300 hover:bg-slate-50'">
                            <input type="radio" :name="`question-${question.id}`" :value="option.id"
                                :checked="answers[question.id] === option.id" :disabled="saving"
                                @change="choose(option.id)" />
                            <span>{{ option.body }}</span>
                        </label>
                    </div>
                </fieldset>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <button type="button" :disabled="saving || answers[question.id] == null"
                        class="text-sm text-slate-600 underline disabled:opacity-40"
                        @click="clearAnswer">
                        Kosongkan jawaban
                    </button>
                    <p class="text-sm" :class="saveState === 'error' ? 'text-red-700' : 'text-slate-600'" role="status" aria-live="polite">
                        {{ saveLabel }}
                    </p>
                </div>
            </article>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex gap-2">
                    <button type="button" :disabled="current === 0"
                        class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-40"
                        @click="current--">
                        Sebelumnya
                    </button>
                    <button type="button" :disabled="current === questions.length - 1"
                        class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-40"
                        @click="current++">
                        Berikutnya
                    </button>
                </div>
                <button type="button" :disabled="saving || submitting"
                    class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @click="confirming = true">
                    Kirim
                </button>
            </div>
        </div>

        <ConfirmDialog
            :open="confirming"
            title="Kirim kuis?"
            :message="(unanswered > 0 ? `Masih ada ${unanswered} soal yang belum dijawab. ` : 'Semua soal sudah dijawab. ') + 'Kuis yang sudah dikirim tidak dapat diubah.'"
            confirm-label="Ya, kirim"
            :busy="submitting"
            @confirm="submit"
            @cancel="confirming = false" />
    </section>
</template>
