<script setup>
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;

const form = ref({ title: '', max_attempts: 1 });
const errors = ref({});
const saving = ref(false);

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        await http.post(`/api/courses/${courseId}/quizzes`, {
            title: form.value.title,
            // Asesmen ulang ditambahkan di Phase 8; saat ini hanya asesmen.
            kind: 'assessment',
            max_attempts: Number(form.value.max_attempts) || 1,
        });
        toast.success('Kuis dibuat sebagai draf.');
        router.push(`/courses/${courseId}/quizzes`);
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

            <p class="text-sm text-slate-600">Jenis kuis: <span class="font-medium text-slate-900">Asesmen</span></p>
            <p v-if="errors.kind" class="text-sm text-red-700">{{ errors.kind[0] }}</p>

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
