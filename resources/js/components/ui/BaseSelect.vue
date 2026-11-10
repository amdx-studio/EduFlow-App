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
        class="block min-h-10 w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100 disabled:text-slate-500"
        :class="invalid ? 'border-danger-500' : 'border-slate-300'"
    >
        <slot />
    </select>
</template>