<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import Badge from './ui/Badge.vue';
import Dropdown from './ui/Dropdown.vue';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const route = useRoute();
const loggingOut = ref(false);

const roleLabel = computed(() => (auth.isTeacher ? 'Guru' : 'Siswa'));
const inHome = computed(() => route.path === '/home');
const inCourses = computed(() => route.path.startsWith('/courses') || route.path.startsWith('/attempts'));
const inAccount = computed(() => route.path === '/account');
const menuItems = [
    { key: 'account', label: 'Akun', icon: 'user' },
    { key: 'logout', label: 'Keluar', icon: 'log-out' },
];

const navClass = (active) => [
    'inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium transition-colors duration-150',
    active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100',
];

function focusMain() {
    document.getElementById('konten')?.focus();
}

async function onSelect(key) {
    if (key === 'account') {
        await router.push({ name: 'account' });
    } else if (key === 'logout') {
        await logout();
    }
}

async function logout() {
    if (loggingOut.value) {
        return;
    }
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
        <a href="#konten" class="skip-link print:hidden" @click.prevent="focusMain">Lewati ke konten</a>
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white print:hidden">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2.5">
                <div class="flex min-w-0 items-center gap-3 sm:gap-6">
                    <RouterLink to="/home" class="shrink-0 text-lg font-bold text-brand-700">EduFlow</RouterLink>
                    <nav aria-label="Utama" class="flex items-center gap-1">
                        <RouterLink to="/home" :class="navClass(inHome)" :aria-current="inHome ? 'page' : undefined">Beranda</RouterLink>
                        <RouterLink to="/courses" :class="navClass(inCourses)" :aria-current="inCourses ? 'page' : undefined">Kelas</RouterLink>
                        <RouterLink to="/account" :class="navClass(inAccount)" :aria-current="inAccount ? 'page' : undefined">Akun</RouterLink>
                    </nav>
                </div>
                <div class="flex min-w-0 items-center gap-3">
                    <span class="hidden sm:inline-flex"><Badge tone="brand">{{ roleLabel }}</Badge></span>
                    <Dropdown :label="auth.user?.name ?? 'Akun'" icon="user" collapse-label :items="menuItems" @select="onSelect" />
                </div>
            </div>
        </header>
        <main id="konten" tabindex="-1" class="mx-auto max-w-6xl px-4 py-6 outline-none">
            <RouterView />
        </main>
    </div>
</template>