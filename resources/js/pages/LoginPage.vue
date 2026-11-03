<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { errorMessage, fieldErrors } from '../lib/errors';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.login({ email: form.email, password: form.password });
        const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
            ? route.query.redirect
            : '/courses';
        await router.push(target);
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
                <span class="border-b-2 border-indigo-600 px-4 py-2 font-semibold text-indigo-700">Masuk</span>
                <RouterLink :to="{ name: 'register' }" class="px-4 py-2 text-slate-600 hover:text-indigo-700">Daftar</RouterLink>
            </div>

            <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
                <p v-if="message" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ message }}</p>

                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-700">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Password</label>
                    <input id="password" v-model="form.password" type="password" autocomplete="current-password" required
                        class="mt-1 block min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="errors.password" class="mt-1 text-sm text-red-700">{{ errors.password[0] }}</p>
                </div>

                <button type="submit" :disabled="submitting"
                    class="min-h-10 w-full rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ submitting ? 'Memproses...' : 'Masuk' }}
                </button>
            </form>
        </div>
    </div>
</template>
