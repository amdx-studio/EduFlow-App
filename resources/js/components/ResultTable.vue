<script setup>
import MasteryBar from './MasteryBar.vue';

// Penguasaan per konsep sebagai ledger (§7.13): nama, "n dari m benar", Skala berzona, persen, glyph + level.
defineProps({
    concepts: { type: Array, default: () => [] },
});
</script>

<template>
    <div>
        <ul class="divide-y divide-line border-y border-line" aria-label="Penguasaan per konsep">
            <li v-for="(concept, i) in concepts" :key="concept.concept_id" class="grid gap-3 py-5 md:grid-cols-[11rem_1fr] md:items-center md:gap-6">
                <div>
                    <p class="text-small font-semibold text-ink">{{ concept.concept }}</p>
                    <p class="text-caption text-kapur-500">{{ concept.correct }} dari {{ concept.total }} benar</p>
                </div>
                <MasteryBar
                    :percent="Number(concept.percent)"
                    :level="concept.level"
                    :label="`Penguasaan ${concept.concept}`"
                    :weak-threshold="concept.weak_threshold_used ?? null"
                    :mastery-threshold="concept.mastery_threshold_used ?? null"
                    :threshold-labels="i === 0"
                    size="lg"
                    animate
                />
            </li>
        </ul>
        <p v-if="concepts.some((c) => c.level === 'insufficient')" class="mt-3 text-caption text-kapur-500">
            "Data kurang" berarti jumlah soal pada konsep itu belum cukup untuk menilai penguasaan.
        </p>
    </div>
</template>
