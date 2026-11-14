<script setup>
const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    invalid: { type: Boolean, default: false },
});
</script>

<template>
    <select
        v-model="model"
        :aria-invalid="invalid || undefined"
        class="block min-h-12 w-full rounded-control border bg-white px-4 py-2 text-body text-ink disabled:bg-kapur-100 disabled:text-kapur-500"
        :class="invalid ? 'border-level-not-mastered-fill' : 'border-line-control'"
    >
        <slot />
    </select>
</template>