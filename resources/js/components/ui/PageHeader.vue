<script setup>
import { RouterLink } from 'vue-router';
import BaseIcon from './BaseIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <nav v-if="breadcrumbs.length" aria-label="Breadcrumb" class="mb-1">
                <ol class="flex flex-wrap items-center gap-1 text-sm text-slate-600">
                    <li v-for="(crumb, i) in breadcrumbs" :key="i" class="flex items-center gap-1">
                        <RouterLink v-if="crumb.to" :to="crumb.to" class="hover:text-brand-700 hover:underline">{{ crumb.label }}</RouterLink>
                        <span v-else aria-current="page">{{ crumb.label }}</span>
                        <BaseIcon v-if="i < breadcrumbs.length - 1" name="chevron-right" size="sm" />
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-slate-600">{{ description }}</p>
            <slot name="meta" />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>