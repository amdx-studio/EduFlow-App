<script setup>
import Badge from './ui/Badge.vue';
import BaseIcon from './ui/BaseIcon.vue';
import DataTable from './ui/DataTable.vue';

defineProps({
    concepts: { type: Array, default: () => [] },
});

// Label selalu berteks; warna bukan satu-satunya pembeda (bagian 10.5).
const TREND = {
    improved: { label: '\u2191 Meningkat', tone: 'success' },
    unchanged: { label: '= Tetap', tone: 'neutral' },
    declined: { label: '\u2193 Menurun', tone: 'danger' },
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
    return TREND[trend] ?? { label: trend, tone: 'neutral' };
}
</script>

<template>
    <DataTable label="Perbandingan per konsep" caption="Perbandingan sebelum dan sesudah per konsep">
        <thead>
            <tr>
                <th scope="col">Konsep</th>
                <th scope="col">Sebelum</th>
                <th scope="col">Sesudah</th>
                <th scope="col">Selisih</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="concept in concepts" :key="concept.concept_id">
                <td class="font-medium">{{ concept.concept }}</td>
                <td class="whitespace-nowrap tabular-nums">{{ formatPercent(concept.before) }}</td>
                <td class="whitespace-nowrap tabular-nums">{{ formatPercent(concept.after) }}</td>
                <td class="whitespace-nowrap tabular-nums">{{ formatDelta(concept.delta) }}</td>
                <td>
                    <Badge :tone="trendMeta(concept.trend).tone">{{ trendMeta(concept.trend).label }}</Badge>
                    <Badge v-if="concept.reached_mastery" tone="success" class="ml-2">
                        <BaseIcon name="check" size="sm" class="mr-1" />Mencapai penguasaan
                    </Badge>
                </td>
            </tr>
        </tbody>
    </DataTable>
</template>