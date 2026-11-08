<script setup>
defineProps({
    concepts: { type: Array, default: () => [] },
});

// Label selalu berteks; warna bukan satu-satunya pembeda (§10.5).
const TREND = {
    improved: { label: '\u2191 Meningkat', cls: 'bg-green-100 text-green-800' },
    unchanged: { label: '= Tetap', cls: 'bg-slate-200 text-slate-700' },
    declined: { label: '\u2193 Menurun', cls: 'bg-red-100 text-red-800' },
};

const percentFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const deltaFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2, signDisplay: 'exceptZero' });

function formatPercent(value) {
    return `${percentFormat.format(value)}%`;
}

function formatDelta(value) {
    return `${deltaFormat.format(value)} poin`;
}

function trendMeta(trend) {
    return TREND[trend] ?? { label: trend, cls: 'bg-slate-200 text-slate-700' };
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-slate-600">
                    <th class="py-2 pr-4 font-medium">Konsep</th>
                    <th class="py-2 pr-4 font-medium">Sebelum</th>
                    <th class="py-2 pr-4 font-medium">Sesudah</th>
                    <th class="py-2 pr-4 font-medium">Selisih</th>
                    <th class="py-2 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="concept in concepts" :key="concept.concept_id" class="border-b border-slate-100">
                    <td class="py-2 pr-4 font-medium">{{ concept.concept }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap tabular-nums">{{ formatPercent(concept.before) }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap tabular-nums">{{ formatPercent(concept.after) }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap tabular-nums">{{ formatDelta(concept.delta) }}</td>
                    <td class="py-2">
                        <span class="inline-block rounded px-2 py-0.5 text-xs font-medium" :class="trendMeta(concept.trend).cls">
                            {{ trendMeta(concept.trend).label }}
                        </span>
                        <span v-if="concept.reached_mastery"
                            class="ml-2 inline-block rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                            &#10003; Mencapai penguasaan
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
