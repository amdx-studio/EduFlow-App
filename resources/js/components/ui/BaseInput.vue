<script setup>
const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    type: { type: String, default: 'text' },
    invalid: { type: Boolean, default: false },
});
</script>

<template>
    <input
        v-model="model"
        :type="type"
        :aria-invalid="invalid || undefined"
        class="block min-h-12 w-full rounded-control border bg-white px-4 py-2 text-body text-ink placeholder:text-kapur-500 disabled:bg-kapur-100 disabled:text-kapur-500"
        :class="invalid ? 'border-level-not-mastered-fill' : 'border-line-control'"
    />
</template>