<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import MarkdownView from '../components/MarkdownView.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import Badge from '../components/ui/Badge.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseSegmented from '../components/ui/BaseSegmented.vue';
import BaseSwitch from '../components/ui/BaseSwitch.vue';
import BaseCheckbox from '../components/ui/BaseCheckbox.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseTextarea from '../components/ui/BaseTextarea.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
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

// Tampilan di bawah 1280 px: segmented Tulis | Pratinjau. Pengaturan dilipat di mobile.
const pane = ref('write');
const paneOptions = [
    { value: 'write', label: 'Tulis' },
    { value: 'preview', label: 'Pratinjau' },
];
const settingsOpen = ref(typeof window === 'undefined' ? true : window.innerWidth >= 768);

// Penanda perubahan belum disimpan: bandingkan dengan keadaan terakhir dimuat/disimpan.
const snapshot = ref('');
const dirty = computed(() => snapshot.value !== '' && JSON.stringify(form.value) !== snapshot.value);

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
        snapshot.value = JSON.stringify(form.value);
        loading.value = false;
    }
}

async function focusFirstError() {
    await nextTick();
    document.querySelector('[aria-invalid="true"]')?.focus();
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
            toast.success('Materi disimpan.');
        } else {
            await http.post(`/api/courses/${courseId}/lessons`, payload);
            toast.success('Materi disimpan.');
        }
        snapshot.value = JSON.stringify(form.value);
        router.push(`/courses/${courseId}/lessons`);
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = fieldErrors(e);
            focusFirstError();
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
        <form @submit.prevent="save">
            <!-- Bar aksi sticky -->
            <div class="sticky top-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 bg-lembar px-1 py-3 shadow-[0_1px_0_var(--color-line)]">
                <div class="flex items-center gap-3">
                    <RouterLink :to="`/courses/${courseId}/lessons`" class="inline-flex items-center gap-1 text-small font-semibold text-samudra-600 hover:underline">
                        <BaseIcon name="arrow-left" size="sm" />Materi
                    </RouterLink>
                    <Badge :tone="form.is_published ? 'success' : 'neutral'">{{ form.is_published ? 'Terbit' : 'Draf' }}</Badge>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="dirty" class="hidden text-caption text-kapur-600 md:inline" role="status">Perubahan belum disimpan</span>
                    <BaseButton variant="ghost" :to="`/courses/${courseId}/lessons`">Batal</BaseButton>
                    <BaseButton type="submit" :loading="saving" :disabled="loading || notFound || !!error">{{ saving ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                </div>
            </div>

            <h2 class="sr-only">{{ isEdit ? 'Ubah materi' : 'Tambah materi' }}</h2>

            <div v-if="loading" class="mt-6 space-y-4" role="status" aria-busy="true">
                <Skeleton class="h-14 w-full" />
                <Skeleton class="h-24 w-full" />
                <Skeleton class="h-80 w-full" />
                <span class="sr-only">Memuat...</span>
            </div>

            <EmptyState v-else-if="notFound" class="mt-6" title="Materi tidak ditemukan." icon="book-open">
                <BaseButton variant="secondary" :to="`/courses/${courseId}/lessons`">Daftar materi</BaseButton>
            </EmptyState>

            <InlineAlert v-else-if="error" tone="danger" class="mt-6" title="Materi tidak dapat dimuat">
                {{ error }}
                <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
            </InlineAlert>

            <div v-else class="mt-6 space-y-8">
                <FormField label="Judul" :error="errors.title?.[0] ?? ''" required v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.title" :aria-describedby="describedby" :invalid="invalid" maxlength="150" required class="min-h-14! font-serif text-h2! font-medium" />
                </FormField>

                <!-- Well Pengaturan -->
                <div class="rounded-tile bg-well p-5 md:p-6">
                    <button type="button" class="flex w-full items-center justify-between text-left text-h3 font-semibold text-ink md:hidden" :aria-expanded="settingsOpen" @click="settingsOpen = !settingsOpen">
                        Pengaturan
                        <BaseIcon :name="settingsOpen ? 'chevron-up' : 'chevron-down'" />
                    </button>
                    <div class="grid-cols-1 gap-6 md:grid md:grid-cols-4" :class="settingsOpen ? 'mt-4 grid md:mt-0' : 'hidden'">
                        <div>
                            <p id="kind-label" class="text-small font-semibold text-ink">Jenis</p>
                            <div class="mt-2">
                                <BaseSegmented v-model="form.kind" label="Jenis materi" :options="kindOptions" />
                            </div>
                            <p v-if="errors.kind?.[0]" class="mt-2 text-small text-level-not-mastered-text" role="alert">{{ errors.kind[0] }}</p>
                        </div>

                        <FormField label="Urutan" :error="errors.position?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <div class="w-24">
                                <BaseInput :id="id" v-model="form.position" :aria-describedby="describedby" :invalid="invalid" type="number" min="0" />
                            </div>
                        </FormField>

                        <div>
                            <p class="text-small font-semibold text-ink">Terbit</p>
                            <div class="mt-2">
                                <BaseSwitch v-model="form.is_published">{{ form.is_published ? 'Terbit' : 'Draf' }}</BaseSwitch>
                            </div>
                            <p class="text-caption text-kapur-500">Siswa hanya melihat materi yang terbit.</p>
                        </div>

                        <fieldset>
                            <legend class="text-small font-semibold text-ink">
                                Konsep
                                <span v-if="form.kind === 'remedial'" class="font-normal text-kapur-600">(wajib untuk remedial)</span>
                            </legend>
                            <p v-if="concepts.length === 0" class="mt-2 text-small text-kapur-600">
                                Belum ada konsep di kelas ini.
                                <RouterLink :to="`/courses/${courseId}/concepts`" class="font-semibold text-samudra-600 underline underline-offset-2">Buat konsep</RouterLink>
                            </p>
                            <div v-else class="mt-2 max-h-40 space-y-1 overflow-y-auto">
                                <BaseCheckbox v-for="concept in concepts" :key="concept.id" v-model="form.concept_ids" :value="concept.id" class="flex min-h-9 items-center">{{ concept.name }}</BaseCheckbox>
                            </div>
                            <p v-if="conceptError" class="mt-2 text-small text-level-not-mastered-text" role="alert">{{ conceptError }}</p>
                        </fieldset>
                    </div>
                </div>

                <!-- Editor dan pratinjau -->
                <div>
                    <div class="mb-4 xl:hidden">
                        <BaseSegmented v-model="pane" label="Tampilan editor" :options="paneOptions" />
                    </div>
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <div :class="pane === 'write' ? 'block' : 'hidden xl:block'">
                            <FormField label="Isi materi" hint="Mendukung Markdown. HTML tidak ditampilkan." :error="errors.content?.[0] ?? ''" required v-slot="{ id, describedby, invalid }">
                                <BaseTextarea :id="id" v-model="form.content" :aria-describedby="describedby" :invalid="invalid" :rows="20" required class="min-h-120 font-mono text-small! leading-[22px]!" />
                            </FormField>
                        </div>
                        <div :class="pane === 'preview' ? 'block' : 'hidden xl:block'">
                            <p class="text-small font-semibold text-ink">Pratinjau</p>
                            <div class="mt-2 min-h-120 rounded-tile bg-well p-6">
                                <MarkdownView v-if="form.content.trim()" :source="form.content" />
                                <p v-else class="text-small text-kapur-500">Pratinjau muncul di sini saat Anda mulai menulis.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
</template>
