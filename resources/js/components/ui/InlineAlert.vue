<script setup>
import { computed } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: '' },
});

const TONES = {
    info: { box: 'border-info-200 bg-info-50 text-info-900', icon: 'info' },
    success: { box: 'border-success-200 bg-success-50 text-success-900', icon: 'check-circle' },
    warning: { box: 'border-warning-200 bg-warning-50 text-warning-900', icon: 'alert-triangle' },
    danger: { box: 'border-danger-200 bg-danger-50 text-danger-900', icon: 'alert-triangle' },
};

const meta = computed(() => TONES[props.tone] ?? TONES.info);
const role = computed(() => (props.tone === 'danger' || props.tone === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm" :class="meta.box" :role="role">
        <BaseIcon :name="meta.icon" class="mt-0.5" />
        <div class="min-w-0">
            <p v-if="title" class="font-semibold">{{ title }}</p>
            <div><slot /></div>
        </div>
    </div>
</template>