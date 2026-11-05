<script setup>
defineProps({
    answers: { type: Array, default: () => [] },
});

function letter(index) {
    return String.fromCharCode(65 + index);
}

function status(answer) {
    if (answer.is_correct) {
        return { text: '\u2713 Benar', cls: 'text-green-800' };
    }
    if (answer.selected_option_id === null) {
        return { text: 'Tidak dijawab', cls: 'text-slate-700' };
    }
    return { text: '\u2717 Salah', cls: 'text-red-800' };
}
</script>

<template>
    <ol class="space-y-3">
        <li v-for="(answer, i) in answers" :key="answer.question_id" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Soal {{ i + 1 }} &middot; {{ answer.concept }}</p>
                <p class="text-sm font-semibold" :class="status(answer).cls">{{ status(answer).text }}</p>
            </div>
            <p class="mt-2 whitespace-pre-line font-medium">{{ answer.body }}</p>

            <ul class="mt-3 space-y-1 text-sm">
                <li v-for="(option, j) in answer.options" :key="option.id"
                    :class="option.id === answer.correct_option_id ? 'font-semibold text-green-800' : 'text-slate-700'">
                    {{ letter(j) }}. {{ option.body }}
                    <span v-if="option.id === answer.selected_option_id"> (jawaban Anda)</span>
                    <span v-if="option.id === answer.correct_option_id"> (jawaban benar)</span>
                </li>
            </ul>

            <p v-if="answer.explanation" class="mt-3 text-sm text-slate-600">Penjelasan: {{ answer.explanation }}</p>
        </li>
    </ol>
</template>
