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
    <!-- Area klik mencakup label (§6.2). Kotak 22 x 22, terpilih isi Samudra 700 dengan tanda putih. -->
    <label class="inline-flex min-h-6 cursor-pointer items-start gap-3 text-body text-ink" :class="attrs.class" :style="attrs.style">
        <input
            v-model="model"
            v-bind="inputAttrs"
            type="checkbox"
            :value="value"
            :aria-invalid="invalid || undefined"
            class="ef-check mt-px shrink-0"
            :class="invalid ? 'ef-check--invalid' : ''"
        />
        <span class="min-w-0"><slot /></span>
    </label>
</template>

<style scoped>
.ef-check {
    appearance: none;
    height: 22px;
    width: 22px;
    border: 1px solid var(--color-kapur-400);
    border-radius: 6px;
    background: #fff center / 14px 14px no-repeat;
    cursor: pointer;
}
.ef-check:hover:not(:disabled) { border-color: var(--color-kapur-500); }
.ef-check:checked {
    border-color: var(--color-samudra-700);
    background-color: var(--color-samudra-700);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 14' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M2.5 7.5l3 3 6-7'/%3E%3C/svg%3E");
}
.ef-check:disabled { opacity: 0.5; cursor: not-allowed; }
.ef-check--invalid { border-color: var(--color-level-not-mastered-fill); }
</style>
