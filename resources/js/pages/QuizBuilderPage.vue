<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import ExportButtons from '../components/ExportButtons.vue';
import PublishPanel from '../components/PublishPanel.vue';
import QuestionEditor from '../components/QuestionEditor.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const courseId = route.params.courseId;
const quizId = route.params.quizId;
const listPath = `/courses/${courseId}/quizzes`;

const quiz = ref(null);
const concepts = ref([]);
const baseQuiz = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const settings = ref({ title: '', max_attempts: 1 });
const settingsErrors = ref({});
const savingSettings = ref(false);

// Akordeon: beberapa soal boleh terbuka bersamaan (§6.8).
const openIds = ref([]);
const adding = ref(false);
const savingKey = ref(null); // id soal atau 'new'
const errorsKey = ref(null);
const editorErrors = ref({});

const deleting = ref(null);
const deleteBusy = ref(false);

const publishPanel = ref(null);
const publishing = ref(false);
const reverting = ref(false);
const publishErrors = ref([]); // [{ message, questionId|null }]
const warnings = ref([]);

const isDraft = computed(() => quiz.value?.status === 'draft');
const isReassessment = computed(() => quiz.value?.kind === 'reassessment');

// Pesan kesalahan publikasi per soal: dipakai untuk garis kiri dan isi butir.
const flaggedByQuestion = computed(() => {
    const map = {};
    for (const item of publishErrors.value) {
        if (item.questionId) {
            (map[item.questionId] ??= []).push(item.message);
        }
    }
    return map;
});

function errorsFor(key) {
    return errorsKey.value === key ? editorErrors.value : {};
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

function toggle(id) {
    openIds.value = openIds.value.includes(id)
        ? openIds.value.filter((value) => value !== id)
        : [...openIds.value, id];
    if (errorsKey.value === id) {
        editorErrors.value = {};
        errorsKey.value = null;
    }
}

function startAdd() {
    adding.value = true;
    errorsKey.value = null;
    editorErrors.value = {};
    nextTick(() => document.getElementById('soal-baru')?.querySelector('textarea')?.focus());
}

function cancelAdd() {
    adding.value = false;
    editorErrors.value = {};
    errorsKey.value = null;
}

async function saveQuestion(key, payload) {
    savingKey.value = key;
    errorsKey.value = null;
    editorErrors.value = {};
    try {
        if (key === 'new') {
            await http.post(`/api/quizzes/${quizId}/questions`, payload);
            toast.success('Soal ditambahkan.');
            adding.value = false;
        } else {
            await http.put(`/api/questions/${key}`, payload);
            toast.success('Soal diperbarui.');
            openIds.value = openIds.value.filter((value) => value !== key);
        }
        publishErrors.value = [];
        await load(true);
    } catch (e) {
        if (e.response?.status === 422) {
            errorsKey.value = key;
            editorErrors.value = fieldErrors(e);
            await nextTick();
            const scope = key === 'new' ? document.getElementById('soal-baru') : document.getElementById(`soal-${key}`);
            scope?.querySelector('[aria-invalid="true"]')?.focus();
        } else {
            toast.error(errorMessage(e));
            await load(true);
        }
    } finally {
        savingKey.value = null;
    }
}

async function removeQuestion() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/questions/${deleting.value.id}`);
        toast.success('Soal dihapus.');
        openIds.value = openIds.value.filter((value) => value !== deleting.value.id);
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

// Kunci kesalahan API-31: "questions.{id}" (per soal), "quiz", "base_quiz_id".
function parsePublishErrors(errors) {
    const items = [];
    for (const [key, messages] of Object.entries(errors)) {
        const match = /^questions\.(\d+)$/.exec(key);
        for (const message of messages) {
            items.push({ message, questionId: match ? Number(match[1]) : null });
        }
    }
    return items;
}

async function publish() {
    publishing.value = true;
    publishErrors.value = [];
    try {
        const { data } = await http.post(`/api/quizzes/${quizId}/publish`);
        warnings.value = data.warnings ?? [];
        toast.success('Kuis diterbitkan.');
        openIds.value = [];
        adding.value = false;
        await load(true);
    } catch (e) {
        if (e.response?.status === 422) {
            publishErrors.value = parsePublishErrors(fieldErrors(e));
            // Soal bermasalah otomatis terbuka; fokus pindah ke ringkasan kesalahan.
            const flagged = publishErrors.value.map((item) => item.questionId).filter(Boolean);
            openIds.value = [...new Set([...openIds.value, ...flagged])];
            await nextTick();
            publishPanel.value?.focusErrors();
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

async function goToQuestion(id) {
    if (!openIds.value.includes(id)) {
        openIds.value = [...openIds.value, id];
    }
    await nextTick();
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    document.getElementById(`soal-${id}`)?.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' });
    document.getElementById(`soal-${id}-btn`)?.focus({ preventScroll: true });
}

onMounted(() => load());
</script>

<template>
    <section>
        <PageHeader
            :title="quiz?.title ?? 'Kuis'"
            :breadcrumbs="[{ label: 'Kuis', to: listPath }, { label: quiz?.title ?? 'Kuis' }]"
        >
            <template v-if="quiz" #meta>
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-small text-kapur-600">
                    <Badge :tone="isDraft ? 'neutral' : 'success'">{{ isDraft ? 'Draf' : 'Terbit' }}</Badge>
                    <Badge :tone="isReassessment ? 'remedial' : 'neutral'">{{ isReassessment ? 'Asesmen ulang' : 'Kuis dasar' }}</Badge>
                    <span v-if="isReassessment">Kuis dasar: {{ baseQuiz?.title ?? `#${quiz.base_quiz_id}` }}</span>
                </div>
            </template>
        </PageHeader>

        <div v-if="loading" class="mt-8 grid gap-10 xl:grid-cols-12" role="status" aria-busy="true">
            <div class="space-y-8 xl:col-span-8">
                <Skeleton class="h-40 w-full" />
                <div class="border-t border-line">
                    <Skeleton v-for="n in 3" :key="n" class="my-3 h-12 w-full" />
                </div>
            </div>
            <Skeleton class="h-56 w-full xl:col-span-4" />
            <span class="sr-only">Memuat kuis...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-8" title="Kuis tidak ditemukan" description="Kuis ini tidak ada atau Anda tidak memiliki akses." icon="alert-triangle">
            <BaseButton variant="secondary" :to="listPath">Daftar kuis</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" class="mt-8" tone="danger" title="Kuis tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load()">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="quiz" class="mt-8 grid gap-10 lg:mt-10 xl:grid-cols-12 xl:items-start">
            <!-- Panel publikasi: di atas daftar soal di bawah 1280 px, kolom kanan sticky di atasnya -->
            <aside aria-label="Publikasi kuis" class="space-y-4 max-xl:order-first xl:sticky xl:top-[72px] xl:col-span-4 xl:col-start-9 xl:row-start-1">
                <PublishPanel
                    ref="publishPanel"
                    :quiz="quiz"
                    :publishing="publishing"
                    :reverting="reverting"
                    :error-items="publishErrors"
                    :warnings="warnings"
                    @publish="publish"
                    @revert="revert"
                    @goto="goToQuestion"
                />
                <ExportButtons v-if="!isDraft" :quiz-id="quizId" />
            </aside>

            <div class="space-y-10 xl:col-span-8 xl:col-start-1 xl:row-start-1 lg:space-y-12">
                <!-- Pengaturan kuis -->
                <section aria-labelledby="pengaturan-title">
                    <h2 id="pengaturan-title" class="font-serif text-h2 font-medium text-ink">Pengaturan kuis</h2>
                    <form class="mt-5 space-y-6" novalidate @submit.prevent="saveSettings">
                        <div class="flex flex-wrap gap-x-4 gap-y-6">
                            <FormField class="min-w-56 flex-1" label="Judul" required :error="settingsErrors.title?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                                <BaseInput :id="id" v-model="settings.title" maxlength="150" required
                                    :aria-describedby="describedby" :invalid="invalid" />
                            </FormField>
                            <FormField label="Percobaan maksimal" required :error="settingsErrors.max_attempts?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                                <div class="w-28">
                                    <BaseInput :id="id" v-model="settings.max_attempts" type="number" min="1" max="65535" required
                                        :aria-describedby="describedby" :invalid="invalid" />
                                </div>
                            </FormField>
                        </div>
                        <p class="text-caption text-kapur-500">
                            Jenis kuis ({{ isReassessment ? 'Asesmen ulang' : 'Kuis dasar' }}) dan kuis dasar ditetapkan saat kuis dibuat dan tidak dapat diubah.
                        </p>
                        <div class="flex md:justify-end">
                            <BaseButton type="submit" variant="secondary" class="max-md:w-full" :loading="savingSettings">
                                {{ savingSettings ? 'Menyimpan...' : 'Simpan pengaturan' }}
                            </BaseButton>
                        </div>
                    </form>
                </section>

                <!-- Soal -->
                <section aria-labelledby="soal-title">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 id="soal-title" class="font-serif text-h2 font-medium text-ink">Soal</h2>
                            <p class="text-caption text-kapur-500">{{ quiz.questions.length }} soal</p>
                        </div>
                        <BaseButton v-if="isDraft && !adding" class="max-md:w-full" @click="startAdd">
                            <BaseIcon name="plus" size="sm" />Tambah soal
                        </BaseButton>
                    </div>

                    <EmptyState
                        v-if="quiz.questions.length === 0 && !adding"
                        class="mt-5"
                        title="Belum ada soal"
                        :description="isDraft ? 'Tambahkan soal pertama dan tautkan ke satu konsep.' : 'Kuis ini terbit tanpa soal.'"
                        icon="clipboard"
                    >
                        <BaseButton v-if="isDraft" @click="startAdd">
                            <BaseIcon name="plus" size="sm" />Tambah soal
                        </BaseButton>
                    </EmptyState>

                    <ol v-if="quiz.questions.length" class="mt-5 border-t border-line">
                        <li v-for="(question, index) in quiz.questions" :key="question.id">
                            <QuestionEditor
                                :question="question"
                                :index="index"
                                :concepts="concepts"
                                :errors="errorsFor(question.id)"
                                :busy="savingKey === question.id"
                                :open="openIds.includes(question.id)"
                                :editable="isDraft"
                                :flagged-messages="flaggedByQuestion[question.id] ?? []"
                                @toggle="toggle(question.id)"
                                @save="(payload) => saveQuestion(question.id, payload)"
                                @cancel="toggle(question.id)"
                                @delete="deleting = question"
                            />
                        </li>
                    </ol>

                    <div v-if="adding" class="mt-6">
                        <QuestionEditor
                            :question="null"
                            :concepts="concepts"
                            :errors="errorsFor('new')"
                            :busy="savingKey === 'new'"
                            @save="(payload) => saveQuestion('new', payload)"
                            @cancel="cancelAdd"
                        />
                    </div>
                </section>
            </div>
        </div>

        <!-- Mobile: bar sticky "Validasi & Terbitkan" di atas bottom nav -->
        <div
            v-if="quiz && isDraft"
            class="sticky bottom-0 z-20 -mx-5 mt-8 border-t border-line bg-white px-5 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:hidden print:hidden"
        >
            <BaseButton class="w-full" :loading="publishing" @click="publish">
                {{ publishing ? 'Memeriksa...' : 'Validasi & Terbitkan' }}
            </BaseButton>
        </div>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus soal ini?"
            message="Soal beserta semua opsinya akan dihapus dari kuis ini dan tidak dapat dikembalikan."
            confirm-label="Ya, hapus soal"
            :busy="deleteBusy"
            @confirm="removeQuestion"
            @cancel="deleting = null" />
    </section>
</template>
