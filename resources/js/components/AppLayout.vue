<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { useCourseList } from '../composables/useCourseList';
import LogoMark from './domain/LogoMark.vue';
import BottomNav from './layout/BottomNav.vue';
import RailNav from './layout/RailNav.vue';
import Sidebar from './layout/Sidebar.vue';
import Topbar from './layout/Topbar.vue';
import UserMenu from './layout/UserMenu.vue';

// AppShell (§5): bingkai Samudra + Lembar putih. Nama berkas lama dipertahankan sebagai alias (§11.6).
// Route Ruang Ujian (meta.layout = 'exam') tampil sebagai layar fokus tanpa bingkai.
const route = useRoute();
const { courses } = useCourseList();
const scrolled = ref(false);
const isExam = computed(() => route.meta.layout === 'exam');

function focusMain() {
    document.getElementById('konten')?.focus();
}

function onScroll(e) {
    scrolled.value = e.target.scrollTop > 4;
}
</script>

<template>
    <div v-if="isExam" class="min-h-dvh bg-white text-ink">
        <a href="#konten" class="skip-link" @click.prevent="focusMain">Lewati ke konten</a>
        <main id="konten" tabindex="-1" class="outline-none">
            <RouterView />
        </main>
    </div>

    <div v-else class="flex h-dvh flex-col bg-frame text-ink md:flex-row print:block print:h-auto">
        <a href="#konten" class="skip-link print:hidden" @click.prevent="focusMain">Lewati ke konten</a>

        <header class="on-frame flex h-14 shrink-0 items-center justify-between bg-frame px-4 md:hidden print:hidden">
            <RouterLink to="/home" class="flex items-center gap-2" aria-label="EduFlow, ke Beranda">
                <LogoMark dark :size="24" />
                <span class="text-lg font-bold text-white">EduFlow</span>
            </RouterLink>
            <UserMenu variant="avatar" placement="down" />
        </header>

        <Sidebar :courses="courses" />
        <RailNav />

        <div class="min-h-0 min-w-0 flex-1 px-2 md:py-2 md:pr-2 md:pl-0 print:p-0">
            <div
                class="h-full overflow-y-auto rounded-tile bg-white md:rounded-lembar print:h-auto print:overflow-visible print:rounded-none"
                @scroll.passive="onScroll"
            >
                <Topbar :scrolled="scrolled" />
                <main id="konten" tabindex="-1" class="mx-auto max-w-300 px-5 pt-6 pb-10 outline-none md:px-8 lg:px-10 2xl:px-14 2xl:pb-14">
                    <RouterView />
                </main>
            </div>
        </div>

        <BottomNav />
    </div>
</template>
