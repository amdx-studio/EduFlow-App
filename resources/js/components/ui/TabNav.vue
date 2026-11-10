<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

defineProps({
    tabs: { type: Array, required: true },
    label: { type: String, default: 'Tab' },
});

const nav = ref(null);
const route = useRoute();

function reveal() {
    nav.value?.querySelector('[aria-current="page"]')?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
}

onMounted(reveal);
watch(() => route.fullPath, () => nextTick(reveal));
</script>

<template>
    <nav ref="nav" :aria-label="label" class="flex overflow-x-auto border-b border-slate-200">
        <RouterLink
            v-for="tab in tabs"
            :key="tab.key ?? tab.label"
            v-slot="{ href, navigate, isActive }"
            :to="tab.to"
            custom
        >
            <a
                :href="href"
                class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors duration-150"
                :class="isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-600 hover:text-brand-700'"
                :aria-current="isActive ? 'page' : undefined"
                @click="navigate"
            >
                {{ tab.label }}
            </a>
        </RouterLink>
    </nav>
</template>