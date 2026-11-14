<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { activeCourse, courseNavItems } from '../../composables/useShell';
import LogoMark from '../domain/LogoMark.vue';
import Monogram from '../domain/Monogram.vue';
import Badge from '../ui/Badge.vue';
import BaseIcon from '../ui/BaseIcon.vue';
import UserMenu from './UserMenu.vue';

defineProps({
    courses: { type: Array, default: () => [] },
});

const route = useRoute();
const auth = useAuthStore();

const GLOBAL = [
    { key: 'home', label: 'Beranda', icon: 'home', to: '/home', on: () => route.path === '/home' },
    { key: 'courses', label: 'Kelas', icon: 'book-open', to: '/courses', on: () => route.path === '/courses' },
    { key: 'account', label: 'Akun', icon: 'user', to: '/account', on: () => route.path === '/account' },
];

const items = computed(() => courseNavItems(activeCourse.value, auth.user?.role));
const inCourse = computed(() => route.path.startsWith('/courses/') && activeCourse.value !== null);
const courseOn = (item) => route.path === item.to || route.path.startsWith(`${item.to}/`);

const base = 'flex items-center gap-3 rounded-control px-3 text-body font-medium transition-colors duration-120';
const idle = 'text-samudra-200 hover:bg-samudra-800 hover:text-white';
const active = 'bg-samudra-700 font-semibold text-white';
</script>

<template>
    <aside class="on-frame hidden w-66 shrink-0 flex-col bg-frame p-4 lg:flex print:hidden" aria-label="Sidebar">
        <RouterLink to="/home" class="flex h-18 items-center gap-3 rounded-control px-2" aria-label="EduFlow, ke Beranda">
            <LogoMark dark :size="28" />
            <span class="text-[22px] leading-none font-bold text-white">EduFlow</span>
        </RouterLink>

        <div class="scroll-hidden min-h-0 flex-1 overflow-y-auto">
            <nav aria-label="Utama" class="flex flex-col gap-1">
                <RouterLink
                    v-for="item in GLOBAL"
                    :key="item.key"
                    :to="item.to"
                    class="min-h-11"
                    :class="[base, item.on() ? active : idle]"
                    :aria-current="item.on() ? 'page' : undefined"
                >
                    <BaseIcon :name="item.icon" :class="item.on() ? 'text-white' : 'text-samudra-300'" />
                    {{ item.label }}
                </RouterLink>
            </nav>

            <section v-if="inCourse" class="mt-6" aria-label="Kelas aktif">
                <div class="flex items-center gap-3 px-2 py-2">
                    <Monogram :title="activeCourse.title" :size="32" />
                    <p class="min-w-0 flex-1 truncate text-body font-semibold text-white">{{ activeCourse.title }}</p>
                    <Badge v-if="activeCourse.is_archived" tone="neutral">Arsip</Badge>
                </div>
                <nav aria-label="Menu kelas" class="mt-1 flex flex-col gap-1 pl-2">
                    <RouterLink
                        v-for="item in items"
                        :key="item.key"
                        :to="item.to"
                        class="min-h-10"
                        :class="[base, courseOn(item) ? active : idle]"
                        :aria-current="courseOn(item) ? 'page' : undefined"
                    >
                        <BaseIcon :name="item.icon" size="sm" :class="courseOn(item) ? 'text-white' : 'text-samudra-300'" />
                        {{ item.label }}
                    </RouterLink>
                </nav>
            </section>

            <section v-else-if="courses.length" class="mt-6" aria-label="Kelas Anda">
                <p class="px-3 pb-2 text-caption font-semibold text-samudra-300">Kelas Anda</p>
                <ul class="flex flex-col gap-1">
                    <li v-for="c in courses.slice(0, 6)" :key="c.id">
                        <RouterLink :to="`/courses/${c.id}`" class="min-h-10 !px-2" :class="[base, idle]">
                            <Monogram :title="c.title" :size="28" />
                            <span class="min-w-0 flex-1 truncate text-small">{{ c.title }}</span>
                        </RouterLink>
                    </li>
                </ul>
                <RouterLink to="/courses" class="mt-1 block px-3 py-2 text-small font-medium text-samudra-300 hover:text-white">Lihat semua</RouterLink>
            </section>
        </div>

        <div class="h-18 shrink-0 border-t border-samudra-800 pt-2">
            <UserMenu variant="block" placement="up" />
        </div>
    </aside>
</template>
