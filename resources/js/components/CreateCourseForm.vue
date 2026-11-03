<script setup>
import { reactive, ref } from 'vue';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const emit = defineEmits(['created', 'cancel']);
const toast = useToastStore();

const form = reactive({
    title: '',
    description: '',
    mastery_threshold: 70,
    weak_threshold: 50,
    min_questions_per_concept: 2,
});
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        const { data } = await http.post('/api/courses', {
            title: form.title,
            description: form.description || null,
            mastery_threshold: Number(form.mastery_threshold),
            weak_threshold: Number(form.weak_threshold),
            min_questions_per_concept: Number(form.min_questions_per_concept),
        });
        toast.success('Kelas berhasil dibuat.');
        emit('created', data.data);
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) {
            message.value = errorMessage(e);
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <form class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm" novalidate @submit.prevent="submit">
        <h2 class="text-lg font-bold">Buat kelas</h2>
        <p v-if="message" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ message }}</p>

        <div>
            <label for="course-title" class="block text-sm font-medium">Judul kelas</label>
            <input id="course-title" v-model="form.title" type="text" maxlength="150" required
                class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
            <p v-if="errors.title" class="mt-1 text-sm text-red-700">{{ errors.title[0] }}</p>
        </div>

        <div>
            <label for="course-desc" class="block text-sm font-medium">Deskripsi (opsional)</label>
            <textarea id="course-desc" v-model="form.description" rows="3"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-700">{{ errors.description[0] }}</p>
        </div>

        <fieldset class="rounded-md border border-slate-200 p-3">
            <legend class="px-1 text-sm font-semibold">Pengaturan penguasaan</legend>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="mastery" class="block text-sm font-medium">Ambang dikuasai (%)</label>
                    <input id="mastery" v-model="form.mastery_threshold" type="number" min="1" max="100"
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.mastery_threshold" class="mt-1 text-sm text-red-700">{{ errors.mastery_threshold[0] }}</p>
                </div>
                <div>
                    <label for="weak" class="block text-sm font-medium">Ambang lemah (%)</label>
                    <input id="weak" v-model="form.weak_threshold" type="number" min="1"
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.weak_threshold" class="mt-1 text-sm text-red-700">{{ errors.weak_threshold[0] }}</p>
                </div>
                <div>
                    <label for="minq" class="block text-sm font-medium">Min. soal per konsep</label>
                    <input id="minq" v-model="form.min_questions_per_concept" type="number" min="1"
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.min_questions_per_concept" class="mt-1 text-sm text-red-700">{{ errors.min_questions_per_concept[0] }}</p>
                </div>
            </div>
        </fieldset>

        <div class="flex justify-end gap-2">
            <button type="button" :disabled="submitting"
                class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100 disabled:opacity-50"
                @click="$emit('cancel')">
                Batal
            </button>
            <button type="submit" :disabled="submitting"
                class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ submitting ? 'Menyimpan...' : 'Buat kelas' }}
            </button>
        </div>
    </form>
</template>