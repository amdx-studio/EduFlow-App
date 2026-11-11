<script setup>
import { computed } from 'vue';
import Badge from './ui/Badge.vue';

const props = defineProps({
    level: { type: String, required: true },
});

// Label selalu berteks; warna bukan satu-satunya pembeda (bagian 10.5).
const META = {
    mastered: { label: 'Dikuasai', tone: 'success' },
    developing: { label: 'Berkembang', tone: 'warning' },
    not_mastered: { label: 'Belum dikuasai', tone: 'danger' },
    insufficient: { label: 'Data kurang', tone: 'neutral' },
};

const meta = computed(() => META[props.level] ?? { label: props.level, tone: 'neutral' });
</script>

<template>
    <Badge :tone="meta.tone">{{ meta.label }}</Badge>
</template>