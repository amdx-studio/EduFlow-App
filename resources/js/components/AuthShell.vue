<script setup>
import BaseCard from './ui/BaseCard.vue';
import TabNav from './ui/TabNav.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const tabs = [
    { key: 'login', label: 'Masuk', to: { name: 'login' } },
    { key: 'register', label: 'Daftar', to: { name: 'register' } },
];
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
        <main class="w-full max-w-md">
            <p class="mb-4 text-center text-2xl font-bold text-brand-700">EduFlow</p>
            <BaseCard>
                <TabNav :tabs="tabs" label="Masuk atau daftar" />
                <h1 class="mt-5 text-xl font-semibold text-slate-900">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-slate-600">{{ description }}</p>
                <div class="mt-4">
                    <slot />
                </div>
            </BaseCard>
        </main>
    </div>
</template>