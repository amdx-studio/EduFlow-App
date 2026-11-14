<script setup>
// Switch 44 x 26 (§6.2): mati Kapur 400, hidup Samudra 700, label di kanan, role="switch".
const model = defineModel({ type: Boolean, default: false });

defineProps({
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <label class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-body text-ink" :class="disabled ? 'cursor-not-allowed opacity-50' : ''">
        <input v-model="model" type="checkbox" role="switch" :aria-checked="model" :disabled="disabled" class="peer sr-only" />
        <span
            class="relative h-6.5 w-11 shrink-0 rounded-full bg-kapur-400 transition-colors duration-150 after:absolute after:left-0.5 after:top-0.5 after:h-5.5 after:w-5.5 after:rounded-full after:bg-white after:transition-transform after:duration-150 peer-checked:bg-samudra-700 peer-checked:after:translate-x-4.5 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-samudra-600"
            aria-hidden="true"
        ></span>
        <span class="min-w-0"><slot /></span>
    </label>
</template>
