<script setup>
import BaseIcon from './BaseIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'clipboard' },
    as: { type: String, default: 'p' },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
        <BaseIcon :name="icon" size="lg" class="text-slate-400" />
        <component :is="as" class="mt-2 font-semibold text-slate-900">{{ title }}</component>
        <p v-if="description" class="mt-1 text-sm text-slate-600">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4 flex flex-wrap justify-center gap-2">
            <slot />
        </div>
    </div>
</template>