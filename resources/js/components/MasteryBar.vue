<script setup>
import { computed, onMounted, ref } from 'vue';
import GlyphLevel from './domain/GlyphLevel.vue';

// Skala Penguasaan (§3.3). Props lama dipertahankan; ambang bersifat opsional.
// Tanpa ambang yang dikonfirmasi (VF-04) skala tampil tanpa zona.
const props = defineProps({
    percent: { type: Number, required: true },
    level: { type: String, default: 'insufficient' },
    label: { type: String, default: 'Penguasaan' },
    weakThreshold: { type: Number, default: null },
    masteryThreshold: { type: Number, default: null },
    size: { type: String, default: 'md' },
    showLevel: { type: Boolean, default: true },
    thresholdLabels: { type: Boolean, default: true },
    animate: { type: Boolean, default: false },
});

const LABEL = {
    mastered: 'Dikuasai',
    developing: 'Berkembang',
    not_mastered: 'Belum dikuasai',
    insufficient: 'Data kurang',
};
const FILL = {
    mastered: 'bg-level-mastered-fill',
    developing: 'bg-level-developing-fill',
    not_mastered: 'bg-level-not-mastered-fill',
    insufficient: 'bg-level-insufficient-fill',
    average: 'bg-samudra-600',
};

const clamp = (n) => Math.min(100, Math.max(0, n));
const width = computed(() => clamp(props.percent));
const text = computed(() => `${props.percent.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`);
const levelLabel = computed(() => LABEL[props.level] ?? 'Rata-rata');
const insufficient = computed(() => props.level === 'insufficient');
const zones = computed(() => props.weakThreshold !== null && props.masteryThreshold !== null);
const weak = computed(() => clamp(props.weakThreshold ?? 0));
const mastery = computed(() => clamp(props.masteryThreshold ?? 0));
const big = computed(() => props.size === 'lg');
const labels = computed(() => big.value && props.thresholdLabels && zones.value);

// Isi tumbuh dari 0 sekali (MI-08); dimatikan otomatis oleh reduced motion di app.css.
const shown = ref(props.animate ? 0 : null);
onMounted(() => {
    if (props.animate) {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            shown.value = null;
        }));
    }
});
const fillWidth = computed(() => (shown.value === null ? width.value : 0));
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="relative w-28 shrink-0 sm:w-40"
            :class="[big ? '!w-full min-w-0 flex-1' : '', labels ? 'pb-5' : '', zones ? 'py-1' : '']"
            role="meter"
            :aria-label="label"
            :aria-valuenow="Math.round(width)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuetext="`${label} ${Math.round(width)} persen, ${levelLabel}`"
        >
            <div class="relative w-full overflow-hidden rounded-full" :class="[big ? 'h-3.5' : 'h-2.5', insufficient ? 'pattern-insufficient' : 'bg-kapur-200']">
                <template v-if="zones && !insufficient">
                    <div class="absolute inset-y-0 left-0 bg-level-not-mastered-tint" :style="{ width: `${weak}%` }"></div>
                    <div class="absolute inset-y-0 bg-level-developing-tint" :style="{ left: `${weak}%`, width: `${mastery - weak}%` }"></div>
                    <div class="absolute inset-y-0 right-0 bg-level-mastered-tint" :style="{ left: `${mastery}%` }"></div>
                </template>
                <div v-if="!insufficient" class="absolute inset-y-0 left-0 rounded-full duration-[600ms] ease-out" :class="[FILL[level] ?? FILL.insufficient, animate ? 'transition-[width]' : '']" :style="{ width: `${fillWidth}%` }"></div>
            </div>
            <template v-if="zones">
                <span class="absolute top-0 w-0.5 bg-kapur-600" :class="labels ? 'bottom-5' : 'bottom-0'" :style="{ left: `${weak}%` }" aria-hidden="true"></span>
                <span class="absolute top-0 w-0.5 bg-kapur-600" :class="labels ? 'bottom-5' : 'bottom-0'" :style="{ left: `${mastery}%` }" aria-hidden="true"></span>
                <template v-if="labels">
                    <span class="absolute bottom-0 -translate-x-1/2 text-caption text-kapur-500 tabular-nums" :style="{ left: `${weak}%` }" aria-hidden="true">{{ weak }}</span>
                    <span class="absolute bottom-0 -translate-x-1/2 text-caption text-kapur-500 tabular-nums" :style="{ left: `${mastery}%` }" aria-hidden="true">{{ mastery }}</span>
                </template>
            </template>
        </div>
        <span class="text-body font-semibold text-ink tabular-nums">{{ text }}</span>
        <span v-if="showLevel && level !== 'average'" class="inline-flex items-center gap-1.5 text-small text-kapur-600">
            <GlyphLevel :level="level" :size="big ? 20 : 16" />
            <span class="hidden sm:inline">{{ levelLabel }}</span>
        </span>
    </div>
</template>
