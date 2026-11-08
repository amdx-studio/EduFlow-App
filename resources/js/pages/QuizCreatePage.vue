<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;

const form = ref({ title: '', kind: 'assessment', base_quiz_id: '', max_attempts: 1 });
const errors = ref({});
const saving = ref(false);

const baseOptions = ref([]);
const loadingBases = ref(true);
const basesFailed = ref(false);

const isReassessment = computed(() => form.value.kind === 'reassessment');

// Kuis dasar yang boleh dipilih: asesmen yang sudah terbit di kelas ini (API-24).
async function loadBases() {
    loadingBases.value = true;
    basesFailed.value = false;
    try {
        const { data } = await http.get(`/api/courses/${courseId}/quizzes`);
        baseOptions.value = data.data.filter((q) => q.kind === 'assessment' && q.status === 'published');
    } catch {
        baseOptions.value = [];
        basesFailed.value = true;
    } finally {
        loadingBases.value = false;
    }
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            title: form.value.title,
            kind: form.value.kind,
            max_attempts: Number(form.value.max_attempts) || 1,
        };
        if (isReassessment.value) {
            payload.base_quiz_id = form.value.base_quiz_id || null;
        }
        const { data } = await http.post(`/api/courses/${courseId}/quizzes`, payload);
        toast.success('Kuis dibuat sebagai draf.');
        router.push(`/courses/${courseId}/quizzes/${data.data.id}`);
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

onMounted(loadBases);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${courseId}/quizzes`" class="text-sm text-indigo-700 hover:underline">
            &larr; Daftar kuis
        </RouterLink>
        <h2 class="mt-2 text-lg font-bold">Buat kuis</h2>

        <form class="mt-3 max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-4" @submit.prevent="save">
            <div>
                <label for="quiz-title" class="block text-sm font-medium">Judul</label>
                <input id="quiz-title" v-model="form.title" type="text" maxlength="150" required
                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p v-if="errors.title" class="mt-1 text-sm text-red-700">{{ errors.title[0] }}</p>
            </div>

            <fieldset>
                <legend class="block text-sm font-medium">Jenis kuis</legend>
                <div class="mt-1 flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex min-h-10 items-center gap-2">
                        <input v-model="form.kind" type="radio" value="assessment" />
                        Asesmen
                    </label>
                    <label class="inline-flex min-h-10 items-center gap-2">
                        <input v-model="form.kind" type="radio" value="reassessment" />
                        Asesmen ulang
                    </label>
                </div>
                <p v-if="errors.kind" class="mt-1 text-sm text-red-700">{{ errors.kind[0] }}</p>
            </fieldset>

            <div v-if="isReassessment">
                <label for="quiz-base" class="block text-sm font-medium">Kuis dasar</label>
                <select id="quiz-base" v-model="form.base_quiz_id" :disabled="loadingBases"
                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">Pilih kuis dasar</option>
                    <option v-for="quiz in baseOptions" :key="quiz.id" :value="quiz.id">{{ quiz.title }}</option>
                </select>
                <p v-if="loadingBases" class="mt-1 text-sm text-slate-600">Memuat daftar kuis...</p>
                <p v-else-if="basesFailed" class="mt-1 text-sm text-red-700">
                    Daftar kuis dasar gagal dimuat.
                    <button type="button" class="underline" @click="loadBases">Coba lagi</button>
                </p>
                <p v-else-if="baseOptions.length === 0" class="mt-1 text-sm text-slate-600">
                    Belum ada asesmen yang terbit di kelas ini. Terbitkan kuis dasar lebih dulu.
                </p>
                <p v-if="errors.base_quiz_id" class="mt-1 text-sm text-red-700">{{ errors.base_quiz_id[0] }}</p>
            </div>

            <div>
                <label for="quiz-attempts" class="block text-sm font-medium">Percobaan maksimal</label>
                <input id="quiz-attempts" v-model.number="form.max_attempts" type="number" min="1" max="65535" required
                    class="mt-1 min-h-10 w-28 rounded-md border border-slate-300 px-3 py-2" />
                <p v-if="errors.max_attempts" class="mt-1 text-sm text-red-700">{{ errors.max_attempts[0] }}</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="saving"
                    class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? 'Menyimpan...' : 'Buat kuis' }}
                </button>
                <RouterLink :to="`/courses/${courseId}/quizzes`"
                    class="inline-flex min-h-10 items-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                    Batal
                </RouterLink>
            </div>
        </form>
    </section>
</template>
