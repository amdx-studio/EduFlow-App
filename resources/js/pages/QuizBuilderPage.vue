<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import ExportButtons from '../components/ExportButtons.vue';
import PublishPanel from '../components/PublishPanel.vue';
import QuestionEditor from '../components/QuestionEditor.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const courseId = route.params.courseId;
const quizId = route.params.quizId;

const quiz = ref(null);
const concepts = ref([]);
const baseQuiz = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const settings = ref({ title: '', max_attempts: 1 });
const settingsErrors = ref({});
const savingSettings = ref(false);

const editing = ref(null); // null | 'new' | id soal
const editorErrors = ref({});
const savingQuestion = ref(false);

const deleting = ref(null);
const deleteBusy = ref(false);

const publishing = ref(false);
const reverting = ref(false);
const publishErrors = ref([]);
const warnings = ref([]);

const isDraft = computed(() => quiz.value?.status === 'draft');

function letter(index) {
    return String.fromCharCode(65 + index);
}

async function load(silent = false) {
    if (!silent) {
        loading.value = true;
    }
    error.value = '';
    notFound.value = false;
    try {
        const [quizRes, conceptsRes] = await Promise.all([
            http.get(`/api/quizzes/${quizId}`),
            http.get(`/api/courses/${courseId}/concepts`),
        ]);
        quiz.value = quizRes.data.data;
        concepts.value = conceptsRes.data.data;
        settings.value = { title: quiz.value.title, max_attempts: quiz.value.max_attempts };
        baseQuiz.value = null;
        if (quiz.value.kind === 'reassessment' && quiz.value.base_quiz_id) {
            try {
                const baseRes = await http.get(`/api/quizzes/${quiz.value.base_quiz_id}`);
                baseQuiz.value = baseRes.data.data;
            } catch {
                baseQuiz.value = null;
            }
        }
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

async function saveSettings() {
    savingSettings.value = true;
    settingsErrors.value = {};
    try {
        await http.put(`/api/quizzes/${quizId}`, {
            title: settings.value.title,
            max_attempts: Number(settings.value.max_attempts) || 1,
        });
        toast.success('Pengaturan kuis disimpan.');
        await load(true);
    } catch (e) {
        if (e.response?.status === 422) {
            settingsErrors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        savingSettings.value = false;
    }
}

function startEdit(id) {
    editing.value = id;
    editorErrors.value = {};
}

function cancelEdit() {
    editing.value = null;
    editorErrors.value = {};
}

async function saveQuestion(payload) {
    savingQuestion.value = true;
    editorErrors.value = {};
    try {
        if (editing.value === 'new') {
            await http.post(`/api/quizzes/${quizId}/questions`, payload);
            toast.success('Soal ditambahkan.');
        } else {
            await http.put(`/api/questions/${editing.value}`, payload);
            toast.success('Soal diperbarui.');
        }
        editing.value = null;
        publishErrors.value = [];
        await load(true);
    } catch (e) {
        if (e.response?.status === 422) {
            editorErrors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
            await load(true);
        }
    } finally {
        savingQuestion.value = false;
    }
}

async function removeQuestion() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/questions/${deleting.value.id}`);
        toast.success('Soal dihapus.');
        publishErrors.value = [];
        await load(true);
    } catch (e) {
        toast.error(errorMessage(e));
        await load(true);
    } finally {
        deleteBusy.value = false;
        deleting.value = null;
    }
}

async function publish() {
    publishing.value = true;
    publishErrors.value = [];
    try {
        const { data } = await http.post(`/api/quizzes/${quizId}/publish`);
        warnings.value = data.warnings ?? [];
        toast.success('Kuis diterbitkan.');
        await load(true);
    } catch (e) {
        if (e.response?.status === 422) {
            publishErrors.value = Object.values(fieldErrors(e)).flat();
        } else {
            toast.error(errorMessage(e));
            await load(true);
        }
    } finally {
        publishing.value = false;
    }
}

async function revert() {
    reverting.value = true;
    try {
        await http.put(`/api/quizzes/${quizId}`, { status: 'draft' });
        warnings.value = [];
        toast.success('Kuis dikembalikan ke draf.');
        await load(true);
    } catch (e) {
        // 409 bila sudah ada percobaan: pesan datang dari API.
        toast.error(errorMessage(e));
        await load(true);
    } finally {
        reverting.value = false;
    }
}

onMounted(() => load());
</script>

<template>
    <section>
        <BaseButton variant="ghost" size="sm" :to="`/courses/${courseId}/quizzes`">
            <BaseIcon name="arrow-left" size="sm" />Daftar kuis
        </BaseButton>

        <div v-if="loading" class="mt-3 space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-7 w-1/2" />
            <Skeleton class="h-24 w-full" />
            <Skeleton class="h-32 w-full" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <div v-else-if="notFound" class="mt-3">
            <EmptyState title="Kuis tidak ditemukan" icon="alert-triangle" />
        </div>

        <InlineAlert v-else-if="error" class="mt-3" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load()">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="quiz" class="mt-3 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold">{{ quiz.title }}</h2>
                <Badge v-if="quiz.kind === 'reassessment'">Asesmen ulang</Badge>
                <span v-if="quiz.kind === 'reassessment'" class="text-sm text-slate-600">
                    Kuis dasar: {{ baseQuiz?.title ?? `#${quiz.base_quiz_id}` }}
                </span>
                <Badge :tone="isDraft ? 'neutral' : 'success'">{{ isDraft ? 'Draf' : 'Terbit' }}</Badge>
            </div>

            <PublishPanel :quiz="quiz" :publishing="publishing" :reverting="reverting"
                :error-messages="publishErrors" :warnings="warnings"
                @publish="publish" @revert="revert" />

            <ExportButtons v-if="!isDraft" :quiz-id="quizId" />

            <BaseCard>
                <form @submit.prevent="saveSettings">
                    <h3 class="font-semibold">Pengaturan kuis</h3>
                    <div class="mt-3 flex flex-wrap items-end gap-4">
                        <FormField class="min-w-48 flex-1" label="Judul" required :error="settingsErrors.title?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseInput :id="id" v-model="settings.title" maxlength="150" required
                                :aria-describedby="describedby" :invalid="invalid" />
                        </FormField>
                        <FormField label="Percobaan maksimal" required :error="settingsErrors.max_attempts?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <div class="w-28">
                                <BaseInput :id="id" v-model="settings.max_attempts" type="number" min="1" max="65535" required
                                    :aria-describedby="describedby" :invalid="invalid" />
                            </div>
                        </FormField>
                        <BaseButton type="submit" variant="secondary" :loading="savingSettings">
                            {{ savingSettings ? 'Menyimpan...' : 'Simpan pengaturan' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">Soal ({{ quiz.questions.length }})</h3>
                    <BaseButton v-if="isDraft && editing === null" @click="startEdit('new')">
                        <BaseIcon name="plus" size="sm" />Tambah soal
                    </BaseButton>
                </div>
                <p v-if="!isDraft" class="mt-1 text-sm text-slate-600">
                    Soal tidak dapat diubah saat kuis terbit. Kembalikan ke draf untuk menyunting.
                </p>

                <EmptyState
                    v-if="quiz.questions.length === 0 && editing !== 'new'"
                    class="mt-3"
                    title="Belum ada soal"
                    description="Tambahkan soal pertama dan tautkan ke sebuah konsep."
                    icon="clipboard"
                />

                <ol class="mt-3 space-y-3">
                    <li v-for="(question, index) in quiz.questions" :key="question.id">
                        <QuestionEditor v-if="editing === question.id" :concepts="concepts" :question="question"
                            :errors="editorErrors" :busy="savingQuestion"
                            @save="saveQuestion" @cancel="cancelEdit" />

                        <BaseCard v-else>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-500">Soal {{ index + 1 }} &middot; nilai urutan {{ question.position }}</p>
                                    <p class="mt-1 whitespace-pre-line font-medium">{{ question.body }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <Badge tone="brand">{{ question.concept?.name }}</Badge>
                                        <Badge>{{ question.points }} poin</Badge>
                                    </div>
                                </div>
                                <div v-if="isDraft" class="flex gap-2">
                                    <BaseButton variant="secondary" size="sm" :disabled="editing !== null" @click="startEdit(question.id)">
                                        <BaseIcon name="edit" size="sm" />Ubah
                                    </BaseButton>
                                    <BaseButton variant="danger-outline" size="sm" :disabled="editing !== null" @click="deleting = question">
                                        <BaseIcon name="trash" size="sm" />Hapus
                                    </BaseButton>
                                </div>
                            </div>
                            <ul class="mt-3 space-y-1 text-sm">
                                <li v-for="(option, i) in question.options" :key="option.id"
                                    :class="option.is_correct ? 'font-semibold text-success-800' : 'text-slate-700'">
                                    {{ letter(i) }}. {{ option.body }}
                                    <span v-if="option.is_correct" class="ml-1 inline-flex items-center gap-0.5 align-middle">
                                        <BaseIcon name="check" size="sm" />benar
                                    </span>
                                </li>
                            </ul>
                            <p v-if="question.explanation" class="mt-2 text-sm text-slate-600">
                                Penjelasan: {{ question.explanation }}
                            </p>
                        </BaseCard>
                    </li>
                </ol>

                <div v-if="editing === 'new'" class="mt-3">
                    <QuestionEditor :concepts="concepts" :question="null" :errors="editorErrors"
                        :busy="savingQuestion" @save="saveQuestion" @cancel="cancelEdit" />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus soal?"
            message="Soal beserta opsinya akan dihapus dari kuis ini."
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="removeQuestion"
            @cancel="deleting = null" />
    </section>
</template>