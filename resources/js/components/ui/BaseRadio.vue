<script setup>
const model = defineModel({ type: [String, Number, Boolean], default: null });

defineProps({
    value: { type: [String, Number, Boolean], required: true },
    name: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <input
        v-model="model"
        type="radio"
        :value="value"
        :name="name || undefined"
        :disabled="disabled"
        class="ef-radio shrink-0"
    />
</template>

<style scoped>
.ef-radio {
    appearance: none;
    height: 22px;
    width: 22px;
    border: 1px solid var(--color-kapur-400);
    border-radius: 9999px;
    background: #fff;
    cursor: pointer;
}
.ef-radio:hover:not(:disabled) { border-color: var(--color-kapur-500); }
.ef-radio:checked {
    border-color: var(--color-samudra-700);
    background: radial-gradient(circle, #fff 0 4px, var(--color-samudra-700) 5px);
}
.ef-radio:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
