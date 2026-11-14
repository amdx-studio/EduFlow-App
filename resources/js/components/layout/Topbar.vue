<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { activeCourse, breadcrumbs } from '../../composables/useShell';
import BaseIcon from '../ui/BaseIcon.vue';
import CopyButton from '../ui/CopyButton.vue';

defineProps({
    scrolled: { type: Boolean, default: false },
});

const auth = useAuthStore();
const code = computed(() => (auth.isTeacher ? activeCourse.value?.join_code ?? '' : ''));
</script>

<template>
    <div
        class="sticky top-0 z-10 hidden h-14 items-center justify-between gap-4 bg-white px-8 transition-colors duration-120 md:flex lg:px-10 2xl:px-14 print:hidden"
        :class="scrolled ? 'border-b border-line' : 'border-b border-transparent'"
    >
        <nav v-if="breadcrumbs.length" aria-label="Breadcrumb" class="min-w-0">
            <ol class="flex items-center gap-1 text-small text-kapur-500">
                <li v-for="(crumb, i) in breadcrumbs" :key="i" class="flex min-w-0 items-center gap-1">
                    <RouterLink v-if="crumb.to" :to="crumb.to" class="truncate hover:text-samudra-600 hover:underline">{{ crumb.label }}</RouterLink>
                    <span v-else class="truncate text-ink" aria-current="page">{{ crumb.label }}</span>
                    <BaseIcon v-if="i < breadcrumbs.length - 1" name="chevron-right" size="sm" />
                </li>
            </ol>
        </nav>
        <span v-else></span>
        <div v-if="code" class="flex shrink-0 items-center gap-2 text-small text-kapur-600">
            Kode
            <code class="rounded-code bg-kapur-50 px-2 py-1 font-mono font-medium tracking-[0.08em] text-ink">{{ code }}</code>
            <CopyButton :value="code" />
        </div>
    </div>
</template>
