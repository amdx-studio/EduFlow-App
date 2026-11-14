<script setup>
import ComparisonScale from './domain/ComparisonScale.vue';
import Badge from './ui/Badge.vue';
import BaseIcon from './ui/BaseIcon.vue';

// Ledger perbandingan (§7.15): ikon + teks untuk tren, selisih bertanda eksplisit.
defineProps({
    concepts: { type: Array, default: () => [] },
});

const TREND = {
    improved: { label: 'Meningkat', icon: 'trending-up', cls: 'text-samudra-700' },
    unchanged: { label: 'Tetap', icon: 'minus', cls: 'text-kapur-600' },
    declined: { label: 'Menurun', icon: 'trending-down', cls: 'text-level-not-mastered-text' },
};

const percentFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const deltaFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2, signDisplay: 'exceptZero' });

const trendMeta = (t) => TREND[t] ?? { label: t, icon: 'minus', cls: 'text-kapur-600' };
const pct = (v) => `${percentFormat.format(v)}%`;
const delta = (v) => (Number(v) === 0 ? '0' : deltaFormat.format(v));

const COLS = 'md:grid md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)_4.5rem_4.5rem_4.5rem_8.5rem] md:items-center md:gap-4';
</script>

<template>
    <div>
        <div class="hidden border-b border-line pb-2 text-caption font-semibold text-kapur-600 md:grid md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)_4.5rem_4.5rem_4.5rem_8.5rem] md:gap-4" aria-hidden="true">
            <span>Konsep</span><span>Perubahan</span><span class="text-right">Sebelum</span><span class="text-right">Sesudah</span><span class="text-right">Selisih</span><span>Status</span>
        </div>
        <ul class="divide-y divide-line border-b border-line md:border-t-0" aria-label="Perbandingan per konsep">
            <li v-for="c in concepts" :key="c.concept_id" class="space-y-3 py-5 md:space-y-0" :class="COLS">
                <div>
                    <p class="text-small font-semibold text-ink">{{ c.concept }}</p>
                    <Badge v-if="c.reached_mastery" tone="success" class="mt-1">
                        <BaseIcon name="check" size="sm" class="mr-1" />Mencapai penguasaan
                    </Badge>
                </div>
                <ComparisonScale :before="Number(c.before)" :after="Number(c.after)" :trend="c.trend" :label="`Perubahan ${c.concept}`" />
                <dl class="flex gap-6 text-small md:contents">
                    <div class="md:text-right"><dt class="text-caption text-kapur-500 md:sr-only">Sebelum</dt><dd class="tabular-nums">{{ pct(c.before) }}</dd></div>
                    <div class="md:text-right"><dt class="text-caption text-kapur-500 md:sr-only">Sesudah</dt><dd class="tabular-nums">{{ pct(c.after) }}</dd></div>
                    <div class="md:text-right"><dt class="text-caption text-kapur-500 md:sr-only">Selisih (poin persentase)</dt><dd class="font-semibold tabular-nums" :class="trendMeta(c.trend).cls">{{ delta(c.delta) }}</dd></div>
                </dl>
                <p class="inline-flex items-center gap-1.5 text-small font-semibold" :class="trendMeta(c.trend).cls">
                    <BaseIcon :name="trendMeta(c.trend).icon" size="sm" />{{ trendMeta(c.trend).label }}
                </p>
            </li>
        </ul>
    </div>
</template>
