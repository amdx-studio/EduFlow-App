<script setup>
import { computed } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: '' },
});

const TONES = {
    info: { box: 'border-info-border bg-info-tint text-info-text', icon: 'info' },
    success: { box: 'border-level-mastered-border bg-level-mastered-tint text-level-mastered-text', icon: 'check-circle' },
    warning: { box: 'border-level-developing-border bg-level-developing-tint text-level-developing-text', icon: 'alert-triangle' },
    danger: { box: 'border-level-not-mastered-border bg-level-not-mastered-tint text-level-not-mastered-text', icon: 'alert-triangle' },
};

const meta = computed(() => TONES[props.tone] ?? TONES.info);
const role = computed(() => (props.tone === 'danger' || props.tone === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div class="flex items-start gap-3 rounded-control border px-4 py-3 text-small" :class="meta.box" :role="role">
        <BaseIcon :name="meta.icon" class="mt-0.5" />
        <div class="min-w-0">
            <p v-if="title" class="font-semibold">{{ title }}</p>
            <div><slot /></div>
        </div>
    </div>
</template>
