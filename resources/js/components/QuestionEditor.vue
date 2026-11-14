<script setup>
import { computed, useId } from 'vue';
import ConceptChip from './domain/ConceptChip.vue';
import QuestionForm from './QuestionForm.vue';
import BaseIcon from './ui/BaseIcon.vue';
import InlineAlert from './ui/InlineAlert.vue';

// QuestionEditor (§7.10): satu soal sebagai butir akordeon (§6.8).
// - question = null  -> soal baru: well "Soal baru" berisi form, selalu terbuka.
// - editable = true  -> isi terbuka berupa form; false -> tampilan baca (kuis terbit).
const props = defineProps({
    question: { type: Object, default: null },
    index: { type: Number, default: 0 },
    concepts: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
    open: { type: Boolean, default: false },
    editable: { type: Boolean, default: true },
    // Pesan kesalahan publikasi untuk soal ini (422 API-31); soal diberi garis kiri 4 px.
    flaggedMessages: { type: Array, default: () => [] },
});
const emit = defineEmits(['toggle', 'save', 'cancel', 'delete']);

const uid = useId();
const isNew = computed(() => props.question === null);
const flagged = computed(() => props.flaggedMessages.length > 0);
const panelId = computed(() => `${uid}-panel`);
const domId = computed(() => (props.question ? `soal-${props.question.id}` : 'soal-baru'));
const letter = (i) => String.fromCharCode(65 + i);
</script>

<template>
    <!-- Soal baru: well dengan form -->
    <div v-if="isNew" :id="domId" class="rounded-tile bg-well p-5 md:p-6">
        <h3 class="font-serif text-h2 font-medium text-ink">Soal baru</h3>
        <QuestionForm
            class="mt-5"
            :concepts="concepts"
            :errors="errors"
            :busy="busy"
            @save="(payload) => emit('save', payload)"
            @cancel="emit('cancel')"
        />
    </div>

    <!-- Butir akordeon -->
    <div
        v-else
        :id="domId"
        class="border-b border-line"
        :class="flagged ? 'border-l-4 border-l-level-not-mastered-fill pl-4' : ''"
    >
        <h3>
            <button
                :id="`${domId}-btn`"
                type="button"
                class="flex min-h-15 w-full items-center gap-4 py-3 text-left"
                :aria-expanded="open"
                :aria-controls="open ? panelId : undefined"
                @click="emit('toggle')"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-kapur-100 font-serif text-body font-medium text-ink" aria-hidden="true">{{ index + 1 }}</span>
                <span class="min-w-0 flex-1">
                    <span class="sr-only">Soal {{ index + 1 }}: </span>
                    <span class="block truncate text-body text-ink">{{ question.body }}</span>
                    <span class="mt-1 flex items-center gap-2 md:hidden">
                        <ConceptChip :name="question.concept?.name ?? 'Tanpa konsep'" />
                        <span class="text-caption text-kapur-500">{{ question.points }} poin</span>
                    </span>
                </span>
                <ConceptChip class="hidden md:inline-flex" :name="question.concept?.name ?? 'Tanpa konsep'" />
                <span class="hidden whitespace-nowrap text-small text-kapur-600 md:block">{{ question.points }} poin</span>
                <BaseIcon name="chevron-down" class="shrink-0 text-kapur-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''" />
            </button>
        </h3>

        <div v-if="open" :id="panelId" role="region" :aria-labelledby="`${domId}-btn`" class="pb-6 md:pl-12">
            <InlineAlert v-if="flagged" class="mb-5" tone="danger" title="Soal ini belum memenuhi syarat terbit">
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="message in flaggedMessages" :key="message">{{ message }}</li>
                </ul>
            </InlineAlert>

            <QuestionForm
                v-if="editable"
                :question="question"
                :concepts="concepts"
                :errors="errors"
                :busy="busy"
                can-delete
                @save="(payload) => emit('save', payload)"
                @cancel="emit('cancel')"
                @delete="emit('delete')"
            />

            <div v-else>
                <p class="whitespace-pre-line text-body text-ink">{{ question.body }}</p>
                <ul class="mt-4 space-y-2">
                    <li v-for="(option, i) in question.options" :key="option.id" class="flex items-start gap-3 text-body">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-small font-semibold"
                            :class="option.is_correct ? 'bg-level-mastered-tint text-level-mastered-text' : 'bg-kapur-100 text-kapur-600'"
                            aria-hidden="true"
                        >{{ letter(i) }}</span>
                        <span class="min-w-0 flex-1 pt-1" :class="option.is_correct ? 'font-semibold text-ink' : 'text-kapur-600'">
                            {{ option.body }}
                            <span v-if="option.is_correct" class="ml-2 inline-flex items-center gap-1 align-middle text-caption font-semibold text-level-mastered-text">
                                <BaseIcon name="check" size="sm" />Jawaban benar
                            </span>
                        </span>
                    </li>
                </ul>
                <p v-if="question.explanation" class="mt-4 text-small text-kapur-600">
                    <span class="font-semibold text-ink">Penjelasan:</span> {{ question.explanation }}
                </p>
            </div>
        </div>
    </div>
</template>
