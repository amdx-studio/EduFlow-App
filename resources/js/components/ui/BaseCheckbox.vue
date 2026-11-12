<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

// Boolean untuk satu kotak, Array untuk sekelompok kotak (isi `value` dikumpulkan).
const model = defineModel({ type: [Boolean, Array], default: false });

defineProps({
    value: { type: [String, Number, Object], default: undefined },
    invalid: { type: Boolean, default: false },
});

const attrs = useAttrs();
const inputAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});
</script>

<template>
    <label class="inline-flex items-start gap-2 text-sm text-slate-900" :class="attrs.class" :style="attrs.style">
        <input
            v-model="model"
            v-bind="inputAttrs"
            type="checkbox"
            :value="value"
            :aria-invalid="invalid || undefined"
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 accent-brand-600 disabled:opacity-50"
            :class="invalid ? 'border-danger-500' : ''"
        />
        <span><slot /></span>
    </label>
</template>