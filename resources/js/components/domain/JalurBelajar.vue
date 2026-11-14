<script setup>
import BaseIcon from '../ui/BaseIcon.vue';

// Jalur Belajar (§7.11): empat langkah berurutan. Status disusun oleh halaman dari data gate dan percobaan.
// step: { key, label, caption, state: 'done' | 'active' | 'locked' | 'todo', accent: 'primary' | 'remedial', to? }
defineProps({
    steps: { type: Array, required: true },
});

const RING = { primary: 'border-samudra-700', remedial: 'border-anggur-600' };
</script>

<template>
    <ol class="relative" aria-label="Jalur belajar">
        <li v-for="(step, i) in steps" :key="step.key" class="relative flex gap-4 pb-8 last:pb-0" :aria-current="step.state === 'active' ? 'step' : undefined">
            <span
                v-if="i < steps.length - 1"
                class="absolute top-8 bottom-0 left-4 -ml-px w-0.5"
                :class="step.state === 'done' ? 'bg-samudra-700' : 'border-l-2 border-dashed border-kapur-300'"
                aria-hidden="true"
            ></span>
            <span
                class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'bg-samudra-700 text-white': step.state === 'done',
                    ['border-[3px] bg-white ' + (RING[step.accent] ?? RING.primary)]: step.state === 'active',
                    'bg-kapur-100 text-kapur-500': step.state === 'locked',
                    'border-2 border-kapur-300 bg-white': step.state === 'todo',
                }"
                aria-hidden="true"
            >
                <BaseIcon v-if="step.state === 'done'" name="check" size="sm" />
                <BaseIcon v-else-if="step.state === 'locked'" name="lock" size="sm" />
            </span>
            <div class="min-w-0 pt-0.5">
                <p class="text-small font-semibold text-ink">{{ step.label }}</p>
                <p class="text-caption text-kapur-600">
                    <RouterLink v-if="step.to" :to="step.to" class="font-semibold text-samudra-600 underline hover:text-samudra-800">{{ step.caption }}</RouterLink>
                    <template v-else>{{ step.caption }}</template>
                </p>
            </div>
        </li>
    </ol>
</template>
