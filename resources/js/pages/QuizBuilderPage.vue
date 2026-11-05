<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import PublishPanel from '../components/PublishPanel.vue';
import QuestionEditor from '../components/QuestionEditor.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const courseId = route.params.courseId;
const quizId = route.params.quizId;

const quiz = ref(null);
const concepts = ref([]);
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
        <RouterLink :to="`/courses/${courseId}/quizzes`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar kuis
        </RouterLink>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat kuis...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Kuis tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load()">Coba lagi</button>
        </div>

        <div v-else-if="quiz" class="mt-3 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-bold">{{ quiz.title }}</h2>
                <span class="rounded px-2 py-0.5 text-xs font-medium"
                    :class="isDraft ? 'bg-slate-200 text-slate-700' : 'bg-green-100 text-green-800'">
                    {{ isDraft ? 'Draf' : 'Terbit' }}
                </span>
            </div>

            <PublishPanel :quiz="quiz" :publishing="publishing" :reverting="reverting"
                :error-messages="publishErrors" :warnings="warnings"
                @publish="publish" @revert="revert" />

            <form class="rounded-lg border border-slate-200 bg-white p-4" @submit.prevent="saveSettings">
                <h3 class="font-semibold">Pengaturan kuis</h3>
                <div class="mt-3 flex flex-wrap items-end gap-4">
                    <div class="min-w-48 flex-1">
                        <label for="quiz-title" class="block text-sm font-medium">Judul</label>
                        <input id="quiz-title" v-model="settings.title" type="text" maxlength="150" required
                            class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                        <p v-if="settingsErrors.title" class="mt-1 text-sm text-red-700">{{ settingsErrors.title[0] }}</p>
                    </div>
                    <div>
                        <label for="quiz-attempts" class="block text-sm font-medium">Percobaan maksimal</label>
                        <input id="quiz-attempts" v-model.number="settings.max_attempts" type="number" min="1" max="65535" required
                            class="mt-1 min-h-10 w-28 rounded-md border border-slate-300 px-3 py-2" />
                        <p v-if="settingsErrors.max_attempts" class="mt-1 text-sm text-red-700">{{ settingsErrors.max_attempts[0] }}</p>
                    </div>
                    <button type="submit" :disabled="savingSettings"
                        class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-50">
                        {{ savingSettings ? 'Menyimpan...' : 'Simpan pengaturan' }}
                    </button>
                </div>
            </form>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">Soal ({{ quiz.questions.length }})</h3>
                    <button v-if="isDraft && editing === null" type="button"
                        class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        @click="startEdit('new')">
                        Tambah soal
                    </button>
                </div>
                <p v-if="!isDraft" class="mt-1 text-sm text-slate-600">
                    Soal tidak dapat diubah saat kuis terbit. Kembalikan ke draf untuk menyunting.
                </p>

                <div v-if="quiz.questions.length === 0 && editing !== 'new'"
                    class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
                    Belum ada soal. Tambahkan soal pertama dan tautkan ke sebuah konsep.
                </div>

                <ol class="mt-3 space-y-3">
                    <li v-for="(question, index) in quiz.questions" :key="question.id">
                        <QuestionEditor v-if="editing === question.id" :concepts="concepts" :question="question"
                            :errors="editorErrors" :busy="savingQuestion"
                            @save="saveQuestion" @cancel="cancelEdit" />

                        <div v-else class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-500">Soal {{ index + 1 }} &middot; nilai urutan {{ question.position }}</p>
                                    <p class="mt-1 whitespace-pre-line font-medium">{{ question.body }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-800">{{ question.concept?.name }}</span>
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">{{ question.points }} poin</span>
                                    </div>
                                </div>
                                <div v-if="isDraft" class="flex gap-2">
                                    <button type="button" :disabled="editing !== null"
                                        class="min-h-10 rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100 disabled:opacity-50"
                                        @click="startEdit(question.id)">
                                        Ubah
                                    </button>
                                    <button type="button" :disabled="editing !== null"
                                        class="min-h-10 rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                                        @click="deleting = question">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                            <ul class="mt-3 space-y-1 text-sm">
                                <li v-for="(option, i) in question.options" :key="option.id"
                                    :class="option.is_correct ? 'font-semibold text-green-800' : 'text-slate-700'">
                                    {{ letter(i) }}. {{ option.body }}
                                    <span v-if="option.is_correct"> &#10003; benar</span>
                                </li>
                            </ul>
                            <p v-if="question.explanation" class="mt-2 text-sm text-slate-600">
                                Penjelasan: {{ question.explanation }}
                            </p>
                        </div>
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
