<script setup>
import { computed } from 'vue';
import LevelBadge from './LevelBadge.vue';
import MasteryBar from './MasteryBar.vue';

const props = defineProps({
    concepts: { type: Array, default: () => [] },
});

const thresholds = computed(() => props.concepts[0] ?? null);
const hasInsufficient = computed(() => props.concepts.some((c) => c.level === 'insufficient'));
</script>

<template>
    <div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="py-2 pr-4 font-medium">Konsep</th>
                        <th class="py-2 pr-4 font-medium">Benar</th>
                        <th class="py-2 pr-4 font-medium">Penguasaan</th>
                        <th class="py-2 font-medium">Level</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="concept in concepts" :key="concept.concept_id" class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium">{{ concept.concept }}</td>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ concept.correct }} dari {{ concept.total }} soal</td>
                        <td class="py-2 pr-4">
                            <MasteryBar :percent="concept.percent" :level="concept.level" :label="`Penguasaan ${concept.concept}`" />
                        </td>
                        <td class="py-2"><LevelBadge :level="concept.level" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="thresholds" class="mt-3 text-xs text-slate-600">
            Ambang saat percobaan ini: dikuasai mulai {{ thresholds.mastery_threshold_used }}%,
            berkembang mulai {{ thresholds.weak_threshold_used }}%.
        </p>
        <p v-if="hasInsufficient" class="mt-1 text-xs text-slate-600">
            &quot;Data kurang&quot; berarti jumlah soal pada konsep itu belum cukup untuk menilai penguasaan.
        </p>
    </div>
</template>
