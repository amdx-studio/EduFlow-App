<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;
const lessonId = route.params.lessonId ?? null;
const isEdit = computed(() => lessonId !== null);

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
        <RouterLink :to="`/courses/${courseId}/lessons`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar materi
        </RouterLink>
        <h2 class="mt-2 text-lg font-bold">{{ isEdit ? 'Ubah materi' : 'Tambah materi' }}</h2>

        <p v-if="loading" class="mt-3 text-slate-600">Memuat...</p>

        <div v-else-if="notFound" class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center">
            <p class="font-semibold">Materi tidak ditemukan.</p>
        </div>

        <div v-else-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <form v-else class="mt-3 space-y-4 rounded-lg border border-slate-200 bg-white p-4" @submit.prevent="save">
            <div>
                <label for="lesson-title" class="block text-sm font-medium">Judul</label>
                <input id="lesson-title" v-model="form.title" type="text" maxlength="150" required
                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p v-if="errors.title" class="mt-1 text-sm text-red-700">{{ errors.title[0] }}</p>
            </div>

            <fieldset>
                <legend class="block text-sm font-medium">Jenis materi</legend>
                <div class="mt-1 flex flex-wrap gap-4">
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.kind" type="radio" value="regular" /> Reguler
                    </label>
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.kind" type="radio" value="remedial" /> Remedial
                    </label>
                </div>
                <p v-if="errors.kind" class="mt-1 text-sm text-red-700">{{ errors.kind[0] }}</p>
            </fieldset>

            <div class="flex flex-wrap items-end gap-6">
                <div>
                    <label for="lesson-position" class="block text-sm font-medium">Urutan</label>
                    <input id="lesson-position" v-model.number="form.position" type="number" min="0"
                        class="mt-1 min-h-10 w-28 rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.position" class="mt-1 text-sm text-red-700">{{ errors.position[0] }}</p>
                </div>
                <label class="flex min-h-10 items-center gap-2 text-sm">
                    <input v-model="form.is_published" type="checkbox" /> Terbitkan (terlihat oleh siswa)
                </label>
            </div>

            <fieldset>
                <legend class="block text-sm font-medium">
                    Konsep terkait
                    <span v-if="form.kind === 'remedial'" class="font-normal text-slate-600">(wajib minimal 1 untuk materi remedial)</span>
                </legend>
                <p v-if="concepts.length === 0" class="mt-1 text-sm text-slate-600">
                    Belum ada konsep di kelas ini.
                    <RouterLink :to="`/courses/${courseId}/concepts`" class="text-indigo-700 underline">Buat konsep</RouterLink>
                </p>
                <div v-else class="mt-1 flex flex-wrap gap-x-4">
                    <label v-for="concept in concepts" :key="concept.id" class="flex min-h-10 items-center gap-2 text-sm">
                        <input v-model="form.concept_ids" type="checkbox" :value="concept.id" /> {{ concept.name }}
                    </label>
                </div>
                <p v-if="conceptError" class="mt-1 text-sm text-red-700">{{ conceptError }}</p>
            </fieldset>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="lesson-content" class="block text-sm font-medium">Isi materi (Markdown)</label>
                    <textarea id="lesson-content" v-model="form.content" rows="14" required
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm"></textarea>
                    <p v-if="errors.content" class="mt-1 text-sm text-red-700">{{ errors.content[0] }}</p>
                </div>
                <div>
                    <p class="block text-sm font-medium">Pratinjau</p>
                    <div class="mt-1 min-h-40 rounded-md border border-slate-200 bg-slate-50 p-3">
                        <MarkdownView :source="form.content" />
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="saving"
                    class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? 'Menyimpan...' : 'Simpan' }}
                </button>
                <RouterLink :to="`/courses/${courseId}/lessons`"
                    class="inline-flex min-h-10 items-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                    Batal
                </RouterLink>
            </div>
        </form>
    </section>
</template>
