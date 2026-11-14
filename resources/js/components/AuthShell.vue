<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import LogoMark from './domain/LogoMark.vue';

// AuthLayout (§7.1): layar terbelah. Panel merek Samudra + panel form putih.
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const route = useRoute();
const isLogin = computed(() => route.name !== 'register');

// Tiga skala abstrak: tanpa angka dan label, hanya penanda gaya (aria-hidden).
const SCALES = [
    { fill: 'w-[62%] bg-samudra-300' },
    { fill: 'w-[84%] bg-samudra-200' },
    { fill: 'w-[38%] bg-samudra-300' },
];
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-white text-ink lg:flex-row">
        <aside class="on-frame flex h-18 shrink-0 flex-col justify-between bg-frame px-6 md:h-45 md:px-10 lg:h-auto lg:min-h-dvh lg:w-5/12 lg:px-14 lg:py-14">
            <div class="flex h-18 items-center gap-3 md:h-auto md:pt-8 lg:pt-0">
                <LogoMark dark :size="36" />
                <span class="text-[28px] leading-none font-bold text-white">EduFlow</span>
            </div>
            <div class="hidden md:block lg:flex-1 lg:pt-24">
                <p class="font-serif text-[34px] leading-10 font-medium tracking-[-0.02em] text-white md:max-w-xl lg:text-display lg:leading-[52px]">
                    Tahu persis konsep mana yang sudah Anda kuasai.
                </p>
                <p class="mt-6 hidden max-w-md text-body-l text-samudra-200 lg:block">
                    EduFlow menunjukkan penguasaan tiap konsep, merekomendasikan remedial, lalu membandingkan hasil sebelum dan sesudah.
                </p>
            </div>
            <div class="hidden flex-col gap-6 lg:flex" aria-hidden="true">
                <div v-for="(scale, i) in SCALES" :key="i" class="relative h-3.5 w-full rounded-full bg-samudra-800">
                    <div class="h-full rounded-full" :class="scale.fill"></div>
                    <span class="absolute -top-1 -bottom-1 left-1/2 w-0.5 bg-emas"></span>
                    <span class="absolute -top-1 -bottom-1 left-[70%] w-0.5 bg-emas"></span>
                </div>
            </div>
        </aside>

        <main class="flex flex-1 items-center justify-center px-6 py-10 md:py-14">
            <div class="w-full max-w-105">
                <h1 class="font-serif text-[28px] leading-[34px] font-medium tracking-[-0.015em] md:text-h1">{{ title }}</h1>
                <p v-if="description" class="mt-2 text-body-l text-kapur-600">{{ description }}</p>
                <div class="mt-8">
                    <slot />
                </div>
                <p class="mt-8 text-body text-kapur-600">
                    <template v-if="isLogin">Belum punya akun? <RouterLink :to="{ name: 'register' }" class="font-semibold text-samudra-600 underline hover:text-samudra-800">Daftar</RouterLink></template>
                    <template v-else>Sudah punya akun? <RouterLink :to="{ name: 'login' }" class="font-semibold text-samudra-600 underline hover:text-samudra-800">Masuk</RouterLink></template>
                </p>
            </div>
        </main>
    </div>
</template>
