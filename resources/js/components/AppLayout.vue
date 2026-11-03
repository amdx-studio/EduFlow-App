<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const loggingOut = ref(false);

const roleLabel = computed(() => (auth.isTeacher ? 'Guru' : 'Siswa'));

async function logout() {
    loggingOut.value = true;
    try {
        await auth.logout();
        toast.success('Anda telah keluar.');
        await router.push({ name: 'login' });
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <RouterLink to="/courses" class="text-lg font-bold text-indigo-700">EduFlow</RouterLink>
                <div class="flex items-center gap-3 text-sm">
                    <span class="truncate">
                        {{ auth.user?.name }}
                        <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ roleLabel }}</span>
                    </span>
                    <button
                        type="button"
                        class="min-h-10 rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-100 disabled:opacity-50"
                        :disabled="loggingOut"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
