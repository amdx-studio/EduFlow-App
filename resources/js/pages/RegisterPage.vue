<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { errorMessage, fieldErrors } from '../lib/errors';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();

const form = reactive({ name: '', email: '', password: '', role: 'student', teacher_code: '' });
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    const payload = { name: form.name, email: form.email, password: form.password, role: form.role };
    if (form.role === 'teacher' && form.teacher_code !== '') {
        payload.teacher_code = form.teacher_code;
    }
    try {
        await auth.register(payload);
        toast.success('Akun berhasil dibuat.');
        await router.push('/courses');
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
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-indigo-700">EduFlow</h1>
            <div class="mt-4 flex border-b border-slate-200 text-sm">
                <RouterLink :to="{ name: 'login' }" class="px-4 py-2 text-slate-600 hover:text-indigo-700">Masuk</RouterLink>
                <span class="border-b-2 border-indigo-600 px-4 py-2 font-semibold text-indigo-700">Daftar</span>
            </div>

            <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
                <p v-if="message" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ message }}</p>

                <div>
                    <label for="name" class="block text-sm font-medium">Nama</label>
                    <input id="name" v-model="form.name" type="text" autocomplete="name" required
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.name" class="mt-1 text-sm text-red-700">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-700">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Password (minimal 8 karakter)</label>
                    <input id="password" v-model="form.password" type="password" autocomplete="new-password" required
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.password" class="mt-1 text-sm text-red-700">{{ errors.password[0] }}</p>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium">Peran</legend>
                    <div class="mt-1 flex gap-6 text-sm">
                        <label class="flex min-h-10 items-center gap-2">
                            <input v-model="form.role" type="radio" value="student" /> Siswa
                        </label>
                        <label class="flex min-h-10 items-center gap-2">
                            <input v-model="form.role" type="radio" value="teacher" /> Guru
                        </label>
                    </div>
                    <p v-if="errors.role" class="mt-1 text-sm text-red-700">{{ errors.role[0] }}</p>
                </fieldset>

                <div v-if="form.role === 'teacher'">
                    <label for="teacher_code" class="block text-sm font-medium">Kode guru</label>
                    <input id="teacher_code" v-model="form.teacher_code" type="text" autocomplete="off"
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.teacher_code" class="mt-1 text-sm text-red-700">{{ errors.teacher_code[0] }}</p>
                </div>

                <button type="submit" :disabled="submitting"
                    class="min-h-10 w-full rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ submitting ? 'Memproses...' : 'Daftar' }}
                </button>
            </form>
        </div>
    </div>
</template>
