<script setup>
import { ref } from 'vue';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const emit = defineEmits(['joined']);
const toast = useToastStore();

const code = ref('');
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        const { data } = await http.post('/api/courses/join', { join_code: code.value });
        toast.success(`Berhasil bergabung ke kelas "${data.data.title}".`);
        code.value = '';
        emit('joined', data.data);
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
    <form class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" novalidate @submit.prevent="submit">
        <label for="join-code" class="block text-sm font-medium">Gabung dengan kode</label>
        <div class="mt-1 flex flex-col gap-2 sm:flex-row">
            <input id="join-code" v-model="code" type="text" maxlength="8" autocomplete="off" placeholder="Contoh: K7M2QX9A"
                class="block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2 font-mono uppercase tracking-wider sm:max-w-xs" />
            <button type="submit" :disabled="submitting || !code.trim()"
                class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ submitting ? 'Memproses...' : 'Gabung' }}
            </button>
        </div>
        <p v-if="errors.join_code" class="mt-2 text-sm text-red-700" role="alert">{{ errors.join_code[0] }}</p>
        <p v-if="message" class="mt-2 text-sm text-red-700" role="alert">{{ message }}</p>
    </form>
</template>