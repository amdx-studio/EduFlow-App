<script setup>
import ConceptChip from './domain/ConceptChip.vue';
import BaseIcon from './ui/BaseIcon.vue';

// Tinjau jawaban (§7.13): akordeon per soal. Status selalu berikon dan berteks.
defineProps({
    answers: { type: Array, default: () => [] },
});

function status(answer) {
    if (answer.is_correct) {
        return { text: 'Benar', icon: 'check', cls: 'text-level-mastered-text' };
    }
    if (answer.selected_option_id === null) {
        return { text: 'Tidak dijawab', icon: 'minus', cls: 'text-kapur-600' };
    }
    return { text: 'Salah', icon: 'x', cls: 'text-level-not-mastered-text' };
}

function optionText(answer, id) {
    return answer.options?.find((o) => o.id === id)?.body ?? '';
}
</script>

<template>
    <ol class="divide-y divide-line border-y border-line">
        <li v-for="(answer, i) in answers" :key="answer.question_id">
            <details class="group">
                <summary class="flex min-h-14 cursor-pointer list-none items-center gap-3 py-3 [&::-webkit-details-marker]:hidden">
                    <BaseIcon name="chevron-right" size="sm" class="text-kapur-500 transition-transform duration-120 group-open:rotate-90" />
                    <span class="text-small font-semibold text-ink">Soal {{ i + 1 }}</span>
                    <span class="inline-flex items-center gap-1 text-small font-semibold" :class="status(answer).cls">
                        <BaseIcon :name="status(answer).icon" size="sm" />{{ status(answer).text }}
                    </span>
                    <ConceptChip :name="answer.concept" class="ml-auto" />
                </summary>
                <div class="pb-6 pl-8">
                    <p class="font-serif text-h3 whitespace-pre-line text-ink">{{ answer.body }}</p>
                    <dl class="mt-4 space-y-2 text-body">
                        <div v-if="answer.selected_option_id !== null" class="flex gap-3">
                            <dt class="w-28 shrink-0 text-kapur-500">Jawaban Anda</dt>
                            <dd class="text-ink">{{ optionText(answer, answer.selected_option_id) }}</dd>
                        </div>
                        <div v-if="optionText(answer, answer.correct_option_id)" class="flex gap-3">
                            <dt class="w-28 shrink-0 text-kapur-500">Jawaban benar</dt>
                            <dd class="font-semibold text-level-mastered-text">{{ optionText(answer, answer.correct_option_id) }}</dd>
                        </div>
                    </dl>
                    <div v-if="answer.explanation" class="mt-4 rounded-control bg-info-tint px-4 py-3 text-body text-info-text">
                        <p class="text-small font-semibold">Penjelasan</p>
                        <p class="mt-1">{{ answer.explanation }}</p>
                    </div>
                </div>
            </details>
        </li>
    </ol>
</template>
