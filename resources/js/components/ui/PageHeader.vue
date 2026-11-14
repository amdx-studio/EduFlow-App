<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { breadcrumbs as shellCrumbs } from '../../composables/useShell';
import BaseIcon from './BaseIcon.vue';

// PageHeader (§5.5): H1 serif, deskripsi, meta, aksi. Breadcrumb tampil di Topbar (≥ 768 px)
// lewat keadaan bersama, dan inline di mobile.
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});

let mine = null;
watch(
    () => props.breadcrumbs,
    (list) => {
        mine = list;
        shellCrumbs.value = list;
    },
    { immediate: true, deep: true },
);
onBeforeUnmount(() => {
    if (shellCrumbs.value === mine) {
        shellCrumbs.value = [];
    }
});
</script>

<template>
    <header class="border-b border-line pb-6">
        <nav v-if="breadcrumbs.length" aria-label="Breadcrumb" class="mb-3 md:hidden">
            <ol class="flex flex-wrap items-center gap-1 text-small text-kapur-500">
                <li v-for="(crumb, i) in breadcrumbs" :key="i" class="flex items-center gap-1">
                    <RouterLink v-if="crumb.to" :to="crumb.to" class="hover:text-samudra-600 hover:underline">{{ crumb.label }}</RouterLink>
                    <span v-else aria-current="page" class="text-ink">{{ crumb.label }}</span>
                    <BaseIcon v-if="i < breadcrumbs.length - 1" name="chevron-right" size="sm" />
                </li>
            </ol>
        </nav>
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between md:gap-6">
            <div class="min-w-0">
                <h1 class="font-serif text-[28px] leading-[34px] font-medium tracking-[-0.015em] text-ink md:text-h1">{{ title }}</h1>
                <p v-if="description" class="mt-2 max-w-prose text-body-l text-kapur-600">{{ description }}</p>
                <slot name="meta" />
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 [&>*:last-child]:max-md:w-full">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
