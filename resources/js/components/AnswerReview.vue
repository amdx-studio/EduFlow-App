<script setup>
import Badge from './ui/Badge.vue';
import BaseCard from './ui/BaseCard.vue';

defineProps({
    answers: { type: Array, default: () => [] },
});

function letter(index) {
    return String.fromCharCode(65 + index);
}

function status(answer) {
    if (answer.is_correct) {
        return { text: '\u2713 Benar', tone: 'success' };
    }
    if (answer.selected_option_id === null) {
        return { text: 'Tidak dijawab', tone: 'neutral' };
    }
    return { text: '\u2717 Salah', tone: 'danger' };
}
</script>

<template>
    <ol class="space-y-3">
        <li v-for="(answer, i) in answers" :key="answer.question_id">
            <BaseCard>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-600">Soal {{ i + 1 }} &middot; {{ answer.concept }}</p>
                    <Badge :tone="status(answer).tone">{{ status(answer).text }}</Badge>
                </div>
                <p class="mt-2 whitespace-pre-line font-medium text-slate-900">{{ answer.body }}</p>

                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="(option, j) in answer.options" :key="option.id"
                        :class="option.id === answer.correct_option_id ? 'font-semibold text-success-800' : 'text-slate-700'">
                        {{ letter(j) }}. {{ option.body }}
                        <span v-if="option.id === answer.selected_option_id"> (jawaban Anda)</span>
                        <span v-if="option.id === answer.correct_option_id"> (jawaban benar)</span>
                    </li>
                </ul>

                <p v-if="answer.explanation" class="mt-3 text-sm text-slate-600">Penjelasan: {{ answer.explanation }}</p>
            </BaseCard>
        </li>
    </ol>
</template>
