<script setup>
import { RouterLink, useRoute } from 'vue-router';
import BaseIcon from '../ui/BaseIcon.vue';

const route = useRoute();
const ITEMS = [
    { key: 'home', label: 'Beranda', icon: 'home', to: '/home', on: () => route.path === '/home' },
    { key: 'courses', label: 'Kelas', icon: 'book-open', to: '/courses', on: () => route.path.startsWith('/courses') || route.path.startsWith('/attempts') },
    { key: 'account', label: 'Akun', icon: 'user', to: '/account', on: () => route.path === '/account' },
];
</script>

<template>
    <nav aria-label="Utama" class="on-frame flex h-17 shrink-0 items-stretch justify-around bg-frame pb-[env(safe-area-inset-bottom)] md:hidden print:hidden">
        <RouterLink
            v-for="item in ITEMS"
            :key="item.key"
            :to="item.to"
            class="flex flex-1 flex-col items-center justify-center gap-1 text-caption font-semibold"
            :class="item.on() ? 'text-white' : 'text-samudra-300'"
            :aria-current="item.on() ? 'page' : undefined"
        >
            <span class="flex h-8 w-14 items-center justify-center rounded-full" :class="item.on() ? 'bg-samudra-700' : ''">
                <BaseIcon :name="item.icon" size="lg" />
            </span>
            {{ item.label }}
        </RouterLink>
    </nav>
</template>
