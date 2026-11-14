<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

// Tab pil horizontal (§5.4): dipakai di tablet dan mobile; di desktop navigasi kelas ada di sidebar.
defineProps({
    tabs: { type: Array, required: true },
    label: { type: String, default: 'Tab' },
});

const nav = ref(null);
const route = useRoute();

// Tab dapat ditandai aktif juga untuk rute lain lewat tab.alsoActive (daftar nama rute).
const isOn = (tab, isActive) => isActive || (tab.alsoActive?.includes(route.name) ?? false);

function reveal() {
    nav.value?.querySelector('[aria-current="page"]')?.scrollIntoView?.({ block: 'nearest', inline: 'center' });
}

onMounted(reveal);
watch(() => route.fullPath, () => nextTick(reveal));
</script>

<template>
    <nav ref="nav" :aria-label="label" class="scroll-hidden flex snap-x gap-2 overflow-x-auto">
        <RouterLink
            v-for="tab in tabs"
            :key="tab.key ?? tab.label"
            v-slot="{ href, navigate, isActive }"
            :to="tab.to"
            custom
        >
            <a
                :href="href"
                class="inline-flex h-10 shrink-0 snap-start items-center rounded-full px-4 text-small font-semibold transition-colors duration-120"
                :class="isOn(tab, isActive) ? 'bg-samudra-700 text-white' : 'text-kapur-600 hover:bg-kapur-100'"
                :aria-current="isOn(tab, isActive) ? 'page' : undefined"
                @click="navigate"
            >
                {{ tab.label }}
            </a>
        </RouterLink>
    </nav>
</template>
