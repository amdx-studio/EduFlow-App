<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: { type: String, required: true },
});

// Label selalu berteks; warna bukan satu-satunya pembeda (§10.5).
const META = {
    mastered: { label: 'Dikuasai', cls: 'bg-green-100 text-green-800' },
    developing: { label: 'Berkembang', cls: 'bg-amber-100 text-amber-900' },
    not_mastered: { label: 'Belum dikuasai', cls: 'bg-red-100 text-red-800' },
    insufficient: { label: 'Data kurang', cls: 'bg-slate-200 text-slate-700' },
};

const meta = computed(() => META[props.level] ?? { label: props.level, cls: 'bg-slate-200 text-slate-700' });
</script>

<template>
    <span class="inline-block rounded px-2 py-0.5 text-xs font-medium" :class="meta.cls">{{ meta.label }}</span>
</template>
