<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';
import ConfirmDialog from './ConfirmDialog.vue';
import BaseButton from './ui/BaseButton.vue';
import LogoMark from './domain/LogoMark.vue';
import BaseIcon from './ui/BaseIcon.vue';
import EmptyState from './ui/EmptyState.vue';
import InlineAlert from './ui/InlineAlert.vue';
import RadioGroup from './ui/RadioGroup.vue';
import Skeleton from './ui/Skeleton.vue';
import Modal from './ui/Modal.vue';

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
const sheet = ref(false);
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const lastFailed = ref(null);
const questionEl = ref(null);
const submitError = ref('');
const submitting = ref(false);

const question = computed(() => questions.value[current.value] ?? null);
const unanswered = computed(() => questions.value.filter((q) => answers.value[q.id] == null).length);
const options = computed(() => (question.value?.options ?? []).map((o) => ({ value: o.id, label: o.body })));

const answeredCount = computed(() => questions.value.length - unanswered.value);
const progress = computed(() => (questions.value.length ? (answeredCount.value / questions.value.length) * 100 : 0));
const unansweredNumbers = computed(() => questions.value.map((q, i) => (answers.value[q.id] == null ? i + 1 : null)).filter(Boolean));
const isLast = computed(() => current.value === questions.value.length - 1);
const confirmMessage = computed(() => {
    const parts = [];
    if (unanswered.value > 0) {
        parts.push(`${unanswered.value} soal belum dijawab (soal ${unansweredNumbers.value.join(', ')}). Soal yang tidak dijawab dinilai 0.`);
    }
    parts.push('Setelah dikirim, jawaban tidak dapat diubah.');
    return parts.join(' ');
});

function setOnline() {
    online.value = navigator.onLine;
}

async function go(index) {
    current.value = index;
    sheet.value = false;
    await nextTick();
    questionEl.value?.focus();
}

function leave() {
    toast.success('Jawaban Anda tersimpan. Lanjutkan kapan saja.');
    router.push(`/courses/${quiz.value.course_id}/quizzes/${quiz.value.id}`);
}

function retry() {
    if (lastFailed.value) {
        save(lastFailed.value.questionId, lastFailed.value.optionId);
    }
}

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
        lastFailed.value = null;
    } catch (e) {
        lastFailed.value = { questionId, optionId };
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
    submitError.value = '';
    try {
        await http.post(`/api/attempts/${props.attemptId}/submit`);
        confirming.value = false;
        toast.success('Kuis dikirim.');
        await goToResult(false);
    } catch (e) {
        // Dialog tetap terbuka agar pengguna dapat mencoba lagi; jawaban aman (rollback total di server).
        submitError.value = e.response ? errorMessage(e) : 'Kuis belum terkirim. Jawaban Anda aman; coba kirim lagi.';
        if (e.response?.status === 409) {
            confirming.value = false;
            toast.error(errorMessage(e));
            await goToResult(true);
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    load();
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', setOnline);
    window.removeEventListener('offline', setOnline);
});
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-white text-ink">
        <header class="relative flex h-14 shrink-0 items-center gap-3 border-b border-line px-4 md:h-16 md:px-6">
            <LogoMark :size="24" />
            <p class="min-w-0 flex-1 truncate text-small font-semibold">{{ quiz?.title ?? 'Kuis' }}</p>
            <p v-if="question" class="flex items-center gap-1.5 text-small" :class="saveState === 'error' ? 'text-level-not-mastered-text' : 'text-kapur-600'" role="status" aria-live="polite">
                <span v-if="saveState === 'saving'" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                <BaseIcon v-else-if="saveState === 'saved'" name="check" size="sm" />
                <span v-if="saveState === 'saving'">Menyimpan…</span>
                <span v-else-if="saveState === 'saved'">Tersimpan</span>
                <template v-else-if="saveState === 'error'">
                    Belum tersimpan
                    <button type="button" class="font-semibold underline" @click="retry">Coba lagi</button>
                </template>
            </p>
            <BaseButton variant="ghost" size="sm" :disabled="!quiz" @click="leave">Keluar</BaseButton>
            <BaseButton v-if="question" variant="secondary" size="sm" class="max-md:hidden" :disabled="saving || submitting" @click="confirming = true">Kirim kuis</BaseButton>
            <div v-if="question" class="absolute inset-x-0 bottom-0 h-1 bg-kapur-200" role="progressbar" aria-label="Soal terjawab" :aria-valuenow="answeredCount" aria-valuemin="0" :aria-valuemax="questions.length">
                <div class="h-full bg-samudra-700 transition-[width] duration-200" :style="{ width: `${progress}%` }"></div>
            </div>
        </header>

        <p v-if="!online" class="bg-level-developing-tint px-4 py-2 text-center text-small text-level-developing-text" role="status">
            Anda sedang offline. Jawaban akan disimpan saat tersambung kembali.
        </p>

        <main id="konten-ujian" class="flex-1 px-4 py-6 md:px-8">
            <div v-if="loading" class="mx-auto max-w-180 space-y-4" role="status" aria-busy="true">
                <Skeleton class="h-8 w-3/4" />
                <Skeleton class="h-16 w-full" />
                <Skeleton class="h-16 w-full" />
                <Skeleton class="h-16 w-full" />
                <Skeleton class="h-16 w-full" />
                <span class="sr-only">Memuat kuis...</span>
            </div>

            <div v-else-if="notFound" class="mx-auto max-w-lg py-16">
                <EmptyState as="h1" title="Percobaan tidak ditemukan" icon="alert-triangle">
                    <BaseButton to="/courses">Kembali ke daftar kelas</BaseButton>
                </EmptyState>
            </div>

            <div v-else-if="error" class="mx-auto max-w-lg py-16">
                <InlineAlert tone="danger" title="Kuis tidak dapat dimuat">
                    {{ error }}
                    <div class="mt-3"><BaseButton variant="secondary" size="sm" @click="load">Muat ulang</BaseButton></div>
                </InlineAlert>
            </div>

            <div v-else-if="question" class="mx-auto max-w-180">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-small font-semibold">Soal {{ current + 1 }} dari {{ questions.length }}</p>
                    <p class="text-caption text-kapur-500">{{ answeredCount }} terjawab</p>
                </div>

                <nav class="mt-3 hidden flex-wrap gap-2 md:flex" aria-label="Nomor soal">
                    <button
                        v-for="(q, i) in questions"
                        :key="q.id"
                        type="button"
                        class="h-11 w-11 rounded-[12px] border text-small transition-colors duration-120"
                        :class="[
                            answers[q.id] != null ? 'border-samudra-100 bg-samudra-100 text-samudra-800' : 'border-line-control bg-white text-ink',
                            i === current ? '!border-2 !border-samudra-700 font-bold' : 'font-medium',
                        ]"
                        :aria-current="i === current ? 'step' : undefined"
                        :aria-label="`Soal ${i + 1}, ${answers[q.id] != null ? 'terjawab' : 'belum dijawab'}`"
                        @click="go(i)"
                    >{{ i + 1 }}</button>
                </nav>
                <BaseButton variant="secondary" size="sm" class="mt-3 md:hidden" @click="sheet = true">Daftar soal</BaseButton>

                <article class="mt-10">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-small font-semibold text-kapur-500">Soal {{ current + 1 }}</p>
                        <p class="text-caption text-kapur-500">{{ question.points }} poin</p>
                    </div>
                    <h1 ref="questionEl" tabindex="-1" class="mt-2 font-serif text-[20px] leading-[30px] font-medium whitespace-pre-line outline-none md:text-[22px] md:leading-8">{{ question.body }}</h1>

                    <div class="mt-6">
                        <RadioGroup
                            :key="question.id"
                            legend="Pilihan jawaban"
                            legend-hidden
                            variant="card"
                            lettered
                            :disabled="saving"
                            :options="options"
                            :model-value="answers[question.id] ?? null"
                            @update:model-value="choose"
                        />
                    </div>

                    <div class="mt-3 flex justify-end">
                        <BaseButton v-if="answers[question.id] != null" variant="ghost" size="sm" :disabled="saving" @click="clearAnswer">Hapus jawaban</BaseButton>
                    </div>
                </article>
            </div>
        </main>

        <footer v-if="question" class="sticky bottom-0 flex h-20 shrink-0 items-center justify-between gap-3 border-t border-line bg-white px-4 md:px-8">
            <div class="mx-auto flex w-full max-w-180 items-center justify-between gap-3">
                <BaseButton variant="secondary" class="max-md:flex-1" :disabled="current === 0" @click="go(current - 1)">Sebelumnya</BaseButton>
                <BaseButton v-if="!isLast" class="max-md:flex-1" @click="go(current + 1)">Berikutnya</BaseButton>
                <BaseButton v-else class="max-md:flex-1" :disabled="saving || submitting" @click="confirming = true">Kirim kuis</BaseButton>
            </div>
        </footer>

        <Modal v-model="sheet" title="Daftar soal" size="sm">
            <div class="grid grid-cols-5 gap-2">
                <button
                    v-for="(q, i) in questions"
                    :key="q.id"
                    type="button"
                    class="h-11 rounded-[12px] border text-small"
                    :class="[
                        answers[q.id] != null ? 'border-samudra-100 bg-samudra-100 text-samudra-800' : 'border-line-control bg-white text-ink',
                        i === current ? '!border-2 !border-samudra-700 font-bold' : 'font-medium',
                    ]"
                    :aria-current="i === current ? 'step' : undefined"
                    :aria-label="`Soal ${i + 1}, ${answers[q.id] != null ? 'terjawab' : 'belum dijawab'}`"
                    @click="go(i)"
                >{{ i + 1 }}</button>
            </div>
        </Modal>

        <ConfirmDialog
            :open="confirming"
            title="Kirim kuis?"
            :message="confirmMessage"
            confirm-label="Kirim kuis"
            cancel-label="Periksa lagi"
            variant="primary"
            :busy="submitting"
            @confirm="submit"
            @cancel="confirming = false; submitError = ''"
        />
        <div v-if="confirming && submitError" class="fixed inset-x-4 bottom-6 z-[60] mx-auto max-w-md">
            <InlineAlert tone="danger">{{ submitError }}</InlineAlert>
        </div>
    </div>
</template>
