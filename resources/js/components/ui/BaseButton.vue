<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    to: { type: [String, Object], default: null },
});

const VARIANTS = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700',
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    danger: 'bg-danger-600 text-white hover:bg-danger-700',
    ghost: 'text-slate-700 hover:bg-slate-100',
    'danger-outline': 'border border-danger-300 bg-white text-danger-700 hover:bg-danger-50',
};
const SIZES = { md: 'min-h-10 px-4 py-2', sm: 'min-h-10 px-3 py-1.5', icon: 'h-8 w-8 p-0' };
</script>

<template>
    <component
        :is="to ? RouterLink : 'button'"
        v-bind="to ? { to } : { type, disabled: disabled || loading }"
        class="inline-flex items-center justify-center gap-2 rounded-lg text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50"
        :class="[VARIANTS[variant] ?? VARIANTS.primary, SIZES[size] ?? SIZES.md]"
        :aria-busy="loading || undefined"
    >
        <span
            v-if="loading"
            class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        ></span>
        <slot />
    </component>
</template>