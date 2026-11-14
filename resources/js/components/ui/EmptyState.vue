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
    <div class="rounded-tile bg-well px-6 py-10 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-samudra-50 text-samudra-600">
            <BaseIcon :name="icon" size="lg" />
        </span>
        <component :is="as" class="mt-4 font-serif text-h2 font-medium text-ink">{{ title }}</component>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-body text-kapur-600">{{ description }}</p>
        <div v-if="$slots.default" class="mt-6 flex flex-wrap justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
