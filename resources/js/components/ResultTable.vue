<script setup>
import { computed } from 'vue';
import LevelBadge from './LevelBadge.vue';
import MasteryBar from './MasteryBar.vue';
import DataTable from './ui/DataTable.vue';

const props = defineProps({
    concepts: { type: Array, default: () => [] },
});

const thresholds = computed(() => props.concepts[0] ?? null);
const hasInsufficient = computed(() => props.concepts.some((c) => c.level === 'insufficient'));
</script>

<template>
    <div>
        <DataTable label="Hasil per konsep" caption="Hasil per konsep pada percobaan ini">
            <thead>
                <tr>
                    <th scope="col">Konsep</th>
                    <th scope="col">Benar</th>
                    <th scope="col">Penguasaan</th>
                    <th scope="col">Level</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="concept in concepts" :key="concept.concept_id">
                    <td class="font-medium">{{ concept.concept }}</td>
                    <td class="whitespace-nowrap">{{ concept.correct }} dari {{ concept.total }} soal</td>
                    <td>
                        <MasteryBar :percent="concept.percent" :level="concept.level" :label="`Penguasaan ${concept.concept}`" />
                    </td>
                    <td><LevelBadge :level="concept.level" /></td>
                </tr>
            </tbody>
        </DataTable>

        <p v-if="thresholds" class="mt-3 text-xs text-slate-600">
            Ambang saat percobaan ini: dikuasai mulai {{ thresholds.mastery_threshold_used }}%,
            berkembang mulai {{ thresholds.weak_threshold_used }}%.
        </p>
        <p v-if="hasInsufficient" class="mt-1 text-xs text-slate-600">
            &quot;Data kurang&quot; berarti jumlah soal pada konsep itu belum cukup untuk menilai penguasaan.
        </p>
    </div>
</template>