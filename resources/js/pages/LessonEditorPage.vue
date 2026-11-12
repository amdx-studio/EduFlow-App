<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseCheckbox from '../components/ui/BaseCheckbox.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseTextarea from '../components/ui/BaseTextarea.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import RadioGroup from '../components/ui/RadioGroup.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;
const lessonId = route.params.lessonId ?? null;
const isEdit = computed(() => lessonId !== null);

const kindOptions = [
    { value: 'regular', label: 'Reguler' },
    { value: 'remedial', label: 'Remedial' },
];

const form = ref({
    title: '',
    content: '',
    kind: 'regular',
    position: 0,
    is_published: false,
    concept_ids: [],
});
const concepts = ref([]);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);
const saving = ref(false);
const errors = ref({});

const conceptError = computed(() => {
    const messages = Object.entries(errors.value)
        .filter(([key]) => key.startsWith('concept_ids'))
        .flatMap(([, value]) => value);
    return messages[0] ?? '';
});

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    try {
        const conceptsRes = await http.get(`/api/courses/${courseId}/concepts`);
        concepts.value = conceptsRes.data.data;

        if (isEdit.value) {
            const { data } = await http.get(`/api/lessons/${lessonId}`);
            const lesson = data.data;
            form.value = {
                title: lesson.title,
                content: lesson.content ?? '',
                kind: lesson.kind,
                position: lesson.position,
                is_published: lesson.is_published,
                concept_ids: (lesson.concepts ?? []).map((c) => c.id),
            };
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

async function save() {
    saving.value = true;
    errors.value = {};

    const payload = {
        title: form.value.title,
        content: form.value.content,
        kind: form.value.kind,
        position: Number(form.value.position) || 0,
        is_published: form.value.is_published,
        concept_ids: form.value.concept_ids,
    };

    try {
        if (isEdit.value) {
            await http.put(`/api/lessons/${lessonId}`, payload);
            toast.success('Materi diperbarui.');
        } else {
            await http.post(`/api/courses/${courseId}/lessons`, payload);
            toast.success('Materi dibuat.');
        }
        router.push(`/courses/${courseId}/lessons`);
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${courseId}/lessons`" class="text-sm text-brand-700 hover:underline">
            &larr; Daftar materi
        </RouterLink>
        <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ isEdit ? 'Ubah materi' : 'Tambah materi' }}</h2>

        <div v-if="loading" class="mt-3 space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-5 w-1/3" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-2/3" />
            <span class="sr-only">Memuat...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-3" title="Materi tidak ditemukan." icon="book-open">
            <BaseButton variant="secondary" :to="`/courses/${courseId}/lessons`">Daftar materi</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-3">
            {{ error }}
            <BaseButton variant="secondary" size="sm" class="ml-2" @click="load">Coba lagi</BaseButton>
        </InlineAlert>

        <BaseCard v-else class="mt-3">
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Judul" :error="errors.title?.[0] ?? ''" required v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.title" :aria-describedby="describedby" :invalid="invalid" maxlength="150" required />
                </FormField>

                <RadioGroup v-model="form.kind" legend="Jenis materi" :options="kindOptions" :error="errors.kind?.[0] ?? ''" />

                <div class="flex flex-wrap items-end gap-6">
                    <FormField label="Urutan" :error="errors.position?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <div class="w-28">
                            <BaseInput :id="id" v-model="form.position" :aria-describedby="describedby" :invalid="invalid" type="number" min="0" />
                        </div>
                    </FormField>
                    <div class="flex min-h-10 items-center">
                        <BaseCheckbox v-model="form.is_published">Terbitkan (terlihat oleh siswa)</BaseCheckbox>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-slate-900">
                        Konsep terkait
                        <span v-if="form.kind === 'remedial'" class="font-normal text-slate-600">(wajib minimal 1 untuk materi remedial)</span>
                    </legend>
                    <p v-if="concepts.length === 0" class="mt-1 text-sm text-slate-600">
                        Belum ada konsep di kelas ini.
                        <RouterLink :to="`/courses/${courseId}/concepts`" class="text-brand-700 underline">Buat konsep</RouterLink>
                    </p>
                    <div v-else class="mt-1 flex flex-wrap gap-x-4">
                        <div v-for="concept in concepts" :key="concept.id" class="flex min-h-10 items-center">
                            <BaseCheckbox v-model="form.concept_ids" :value="concept.id">{{ concept.name }}</BaseCheckbox>
                        </div>
                    </div>
                    <p v-if="conceptError" class="mt-1 text-sm text-danger-700" role="alert">{{ conceptError }}</p>
                </fieldset>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField label="Isi materi (Markdown)" :error="errors.content?.[0] ?? ''" required v-slot="{ id, describedby, invalid }">
                        <BaseTextarea :id="id" v-model="form.content" :aria-describedby="describedby" :invalid="invalid" :rows="14" required class="font-mono" />
                    </FormField>
                    <div>
                        <p class="block text-sm font-medium text-slate-900">Pratinjau</p>
                        <div class="mt-1 min-h-40 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <MarkdownView :source="form.content" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <BaseButton type="submit" :loading="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                    <BaseButton variant="secondary" :to="`/courses/${courseId}/lessons`">Batal</BaseButton>
                </div>
            </form>
        </BaseCard>
    </section>
</template>
