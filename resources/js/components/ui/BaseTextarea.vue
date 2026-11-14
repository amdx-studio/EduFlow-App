<script setup>
const model = defineModel({ type: String, default: '' });

defineProps({
    rows: { type: Number, default: 4 },
    invalid: { type: Boolean, default: false },
});
</script>

<template>
    <textarea
        v-model="model"
        :rows="rows"
        :aria-invalid="invalid || undefined"
        class="block w-full rounded-control border bg-white px-4 py-3 text-body text-ink placeholder:text-kapur-500 disabled:bg-kapur-100 disabled:text-kapur-500"
        :class="invalid ? 'border-level-not-mastered-fill' : 'border-line-control'"
    ></textarea>
</template>