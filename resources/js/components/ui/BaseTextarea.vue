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
        class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 disabled:bg-slate-100 disabled:text-slate-500"
        :class="invalid ? 'border-danger-500' : 'border-slate-300'"
    ></textarea>
</template>