<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';
import ConfirmDialog from './ConfirmDialog.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseCard from './ui/BaseCard.vue';
import BaseIcon from './ui/BaseIcon.vue';
import EmptyState from './ui/EmptyState.vue';
import InlineAlert from './ui/InlineAlert.vue';
import RadioGroup from './ui/RadioGroup.vue';
import Skeleton from './ui/Skeleton.vue';

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
const options = computed(() => (question.value?.options ?? []).map((o) => ({ value: o.id, label: o.body })));

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
        <div v-if="loading" class="space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-6 w-1/2" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-40 w-full" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <EmptyState v-else-if="notFound" as="h1" title="Percobaan tidak ditemukan" icon="alert-triangle" />

        <InlineAlert v-else-if="error" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="question" class="space-y-4">
            <div>
                <h1 class="text-lg font-semibold">{{ quiz.title }}</h1>
                <p class="text-sm text-slate-600">Percobaan ke-{{ attemptNo }}</p>
            </div>

            <nav class="flex flex-wrap gap-2" aria-label="Nomor soal">
                <BaseButton v-for="(q, i) in questions" :key="q.id"
                    :variant="i === current ? 'primary' : 'secondary'" size="sm" class="min-w-10"
                    :aria-current="i === current ? 'step' : undefined"
                    :aria-label="`Soal ${i + 1}, ${answers[q.id] != null ? 'sudah dijawab' : 'belum dijawab'}`"
                    @click="current = i">
                    {{ i + 1 }}<BaseIcon v-if="answers[q.id] != null" name="check" size="sm" />
                </BaseButton>
            </nav>

            <BaseCard>
                <article>
                    <p class="text-xs text-slate-500">Soal {{ current + 1 }} dari {{ questions.length }} &middot; {{ question.points }} poin</p>
                    <p class="mt-2 whitespace-pre-line font-medium">{{ question.body }}</p>

                    <div class="mt-4">
                        <RadioGroup :key="question.id" legend="Pilihan jawaban" legend-hidden variant="card"
                            :disabled="saving" :options="options" :model-value="answers[question.id] ?? null"
                            @update:model-value="choose" />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <BaseButton variant="ghost" size="sm" :disabled="saving || answers[question.id] == null" @click="clearAnswer">
                            Kosongkan jawaban
                        </BaseButton>
                        <p class="text-sm" :class="saveState === 'error' ? 'text-danger-700' : 'text-slate-600'" role="status" aria-live="polite">
                            {{ saveLabel }}
                        </p>
                    </div>
                </article>
            </BaseCard>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex gap-2">
                    <BaseButton variant="secondary" :disabled="current === 0" @click="current--">
                        <BaseIcon name="chevron-left" size="sm" />Sebelumnya
                    </BaseButton>
                    <BaseButton variant="secondary" :disabled="current === questions.length - 1" @click="current++">
                        Berikutnya<BaseIcon name="chevron-right" size="sm" />
                    </BaseButton>
                </div>
                <BaseButton :disabled="saving || submitting" @click="confirming = true">Kirim</BaseButton>
            </div>
        </div>

        <ConfirmDialog
            :open="confirming"
            title="Kirim kuis?"
            :message="(unanswered > 0 ? `Masih ada ${unanswered} soal yang belum dijawab. ` : 'Semua soal sudah dijawab. ') + 'Kuis yang sudah dikirim tidak dapat diubah.'"
            confirm-label="Ya, kirim"
            variant="primary"
            :busy="submitting"
            @confirm="submit"
            @cancel="confirming = false" />
    </section>
</template>