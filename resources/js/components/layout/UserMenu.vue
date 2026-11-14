<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import BaseIcon from '../ui/BaseIcon.vue';

// Menu pengguna (Akun, Keluar). variant "block" di sidebar, "avatar" di rail dan header mobile.
const props = defineProps({
    variant: { type: String, default: 'block' },
    placement: { type: String, default: 'up' },
});

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();

const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const menu = ref(null);
const loggingOut = ref(false);

const name = computed(() => auth.user?.name ?? 'Akun');
const initial = computed(() => (name.value.trim()[0] ?? '?').toUpperCase());
const role = computed(() => (auth.isTeacher ? 'Guru' : 'Siswa'));
const roleClass = computed(() => (auth.isTeacher ? 'text-emas' : 'text-samudra-200'));

function items() {
    return menu.value ? Array.from(menu.value.querySelectorAll('[role="menuitem"]')) : [];
}

async function show() {
    open.value = true;
    document.addEventListener('pointerdown', onOutside);
    await nextTick();
    items()[0]?.focus();
}

function hide(returnFocus = false) {
    open.value = false;
    document.removeEventListener('pointerdown', onOutside);
    if (returnFocus) {
        trigger.value?.focus();
    }
}

function onOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        hide(false);
    }
}

function onKey(e) {
    const list = items();
    const i = list.indexOf(document.activeElement);
    if (e.key === 'Escape') {
        e.preventDefault();
        hide(true);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        list[(i + 1) % list.length]?.focus();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        list[(i - 1 + list.length) % list.length]?.focus();
    } else if (e.key === 'Tab') {
        hide(false);
    }
}

async function goAccount() {
    hide(false);
    await router.push({ name: 'account' });
}

async function logout() {
    if (loggingOut.value) {
        return;
    }
    loggingOut.value = true;
    hide(false);
    try {
        await auth.logout();
        toast.success('Anda telah keluar.');
        await router.push({ name: 'login' });
    } finally {
        loggingOut.value = false;
    }
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<template>
    <div ref="root" class="relative" @keydown="onKey">
        <button
            ref="trigger"
            type="button"
            class="flex items-center gap-3 rounded-control text-left hover:bg-samudra-800"
            :class="variant === 'block' ? 'w-full p-2' : 'p-1'"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-label="variant === 'block' ? undefined : `Menu akun ${name}`"
            @click="open ? hide(false) : show()"
        >
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-samudra-700 text-small font-semibold text-white" aria-hidden="true">{{ initial }}</span>
            <span v-if="variant === 'block'" class="min-w-0 flex-1">
                <span class="block truncate text-small font-semibold text-white">{{ name }}</span>
                <span class="mt-0.5 inline-flex rounded-full bg-samudra-800 px-2 text-caption font-semibold" :class="roleClass">{{ role }}</span>
            </span>
            <BaseIcon v-if="variant === 'block'" name="chevron-up" size="sm" class="text-samudra-300" />
        </button>
        <div
            v-if="open"
            ref="menu"
            role="menu"
            :aria-label="`Akun ${name}`"
            class="absolute z-40 w-56 rounded-control bg-white py-1 shadow-popover"
            :class="[placement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2', variant === 'block' ? 'left-0' : (placement === 'up' ? 'left-0' : 'right-0')]"
        >
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-small text-ink hover:bg-kapur-100 focus:bg-kapur-100 -outline-offset-2" @click="goAccount">
                <BaseIcon name="user" size="sm" /> Akun
            </button>
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-small text-ink hover:bg-kapur-100 focus:bg-kapur-100 -outline-offset-2" @click="logout">
                <BaseIcon name="log-out" size="sm" /> Keluar
            </button>
        </div>
    </div>
</template>
