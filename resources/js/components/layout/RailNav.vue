<script setup>
import { RouterLink, useRoute } from 'vue-router';
import LogoMark from '../domain/LogoMark.vue';
import BaseIcon from '../ui/BaseIcon.vue';
import UserMenu from './UserMenu.vue';

const route = useRoute();
const ITEMS = [
    { key: 'home', label: 'Beranda', icon: 'home', to: '/home', on: () => route.path === '/home' },
    { key: 'courses', label: 'Kelas', icon: 'book-open', to: '/courses', on: () => route.path.startsWith('/courses') || route.path.startsWith('/attempts') },
    { key: 'account', label: 'Akun', icon: 'user', to: '/account', on: () => route.path === '/account' },
];
</script>

<template>
    <aside class="on-frame hidden w-19 shrink-0 flex-col items-center bg-frame py-4 md:flex lg:hidden print:hidden" aria-label="Rail">
        <RouterLink to="/home" class="flex h-14 items-center" aria-label="EduFlow, ke Beranda"><LogoMark dark :size="28" /></RouterLink>
        <nav aria-label="Utama" class="mt-2 flex flex-1 flex-col gap-2">
            <RouterLink
                v-for="item in ITEMS"
                :key="item.key"
                :to="item.to"
                :title="item.label"
                :aria-label="item.label"
                :aria-current="item.on() ? 'page' : undefined"
                class="group relative flex h-13 w-13 items-center justify-center rounded-[14px] transition-colors duration-120"
                :class="item.on() ? 'bg-samudra-700 text-white' : 'text-samudra-300 hover:bg-samudra-800 hover:text-white'"
            >
                <BaseIcon :name="item.icon" size="lg" />
                <span class="pointer-events-none absolute left-full z-40 ml-3 hidden rounded-code bg-samudra-950 px-2 py-1 text-caption font-semibold whitespace-nowrap text-white group-hover:block group-focus-visible:block" aria-hidden="true">{{ item.label }}</span>
            </RouterLink>
        </nav>
        <UserMenu variant="avatar" placement="up" />
    </aside>
</template>
