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
    primary: 'bg-primary text-white hover:bg-primary-hover active:bg-primary-active',
    secondary: 'border border-line-control bg-white text-ink hover:bg-kapur-100 active:bg-kapur-200',
    danger: 'bg-level-not-mastered-fill text-white hover:bg-level-not-mastered-text active:bg-level-not-mastered-text',
    ghost: 'text-kapur-600 hover:bg-kapur-100 active:bg-kapur-200',
    'danger-outline': 'border border-level-not-mastered-border bg-white text-level-not-mastered-text hover:bg-level-not-mastered-tint',
};
const SIZES = {
    md: 'min-h-11 px-5 py-2',
    sm: 'min-h-9 px-4 py-1.5',
    lg: 'min-h-13 px-6 py-3 text-body-l',
    icon: 'h-9 w-9 p-0',
};
</script>

<template>
    <component
        :is="to ? RouterLink : 'button'"
        v-bind="to ? { to } : { type, disabled: disabled || loading }"
        class="inline-flex items-center justify-center gap-2 rounded-control text-small font-semibold transition-colors duration-120 disabled:cursor-not-allowed disabled:opacity-50"
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
