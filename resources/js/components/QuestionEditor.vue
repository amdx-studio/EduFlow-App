<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const props = defineProps({
    concepts: { type: Array, default: () => [] },
    question: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['save', 'cancel']);

const route = useRoute();

const MIN_OPTIONS = 2;
const MAX_OPTIONS = 6;

function blank() {
    return {
        body: '',
        concept_id: '',
        points: 1,
        explanation: '',
        options: [
            { body: '', is_correct: true },
            { body: '', is_correct: false },
        ],
    };
}

function fromQuestion(q) {
    return {
        body: q.body,
        concept_id: q.concept_id,
        points: q.points,
        explanation: q.explanation ?? '',
        options: (q.options ?? []).map((o) => ({ body: o.body, is_correct: o.is_correct })),
    };
}

const form = ref(props.question ? fromQuestion(props.question) : blank());
watch(() => props.question, (q) => {
    form.value = q ? fromQuestion(q) : blank();
});

const optionErrors = computed(() => {
    const messages = Object.entries(props.errors)
        .filter(([key]) => key.startsWith('options'))
        .flatMap(([, value]) => value);
    return [...new Set(messages)];
});

function setCorrect(index) {
    form.value.options.forEach((option, i) => {
        option.is_correct = i === index;
    });
}

function addOption() {
    if (form.value.options.length < MAX_OPTIONS) {
        form.value.options.push({ body: '', is_correct: false });
    }
}

function removeOption(index) {
    if (form.value.options.length <= MIN_OPTIONS) {
        return;
    }
    const wasCorrect = form.value.options[index].is_correct;
    form.value.options.splice(index, 1);
    if (wasCorrect) {
        form.value.options[0].is_correct = true;
    }
}

function submit() {
    emit('save', {
        body: form.value.body,
        concept_id: Number(form.value.concept_id),
        points: Number(form.value.points) || 1,
        explanation: form.value.explanation || null,
        options: form.value.options.map((o) => ({ body: o.body, is_correct: !!o.is_correct })),
    });
}
</script>

<template>
    <form class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4" @submit.prevent="submit">
        <h3 class="font-semibold">{{ question ? 'Ubah soal' : 'Tambah soal' }}</h3>

        <div>
            <label for="qe-body" class="block text-sm font-medium">Teks soal</label>
            <textarea id="qe-body" v-model="form.body" rows="3" required
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2"></textarea>
            <p v-if="errors.body" class="mt-1 text-sm text-red-700">{{ errors.body[0] }}</p>
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="min-w-48 flex-1">
                <label for="qe-concept" class="block text-sm font-medium">Konsep</label>
                <select id="qe-concept" v-model="form.concept_id" required
                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2">
                    <option value="" disabled>Pilih konsep</option>
                    <option v-for="concept in concepts" :key="concept.id" :value="concept.id">{{ concept.name }}</option>
                </select>
                <p v-if="concepts.length === 0" class="mt-1 text-sm text-slate-600">
                    Belum ada konsep.
                    <RouterLink :to="`/courses/${route.params.courseId}/concepts`" class="text-indigo-700 underline">Buat konsep</RouterLink>
                </p>
                <p v-if="errors.concept_id" class="mt-1 text-sm text-red-700">{{ errors.concept_id[0] }}</p>
            </div>
            <div>
                <label for="qe-points" class="block text-sm font-medium">Poin</label>
                <input id="qe-points" v-model.number="form.points" type="number" min="1" max="65535" required
                    class="mt-1 min-h-10 w-24 rounded-md border border-slate-300 bg-white px-3 py-2" />
                <p v-if="errors.points" class="mt-1 text-sm text-red-700">{{ errors.points[0] }}</p>
            </div>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium">Opsi jawaban (pilih satu yang benar)</legend>
            <div class="mt-2 space-y-2">
                <div v-for="(option, i) in form.options" :key="i" class="flex items-center gap-2">
                    <input type="radio" name="qe-correct" :checked="option.is_correct"
                        :aria-label="`Opsi ${i + 1} benar`" @change="setCorrect(i)" />
                    <input v-model="option.body" type="text" required :aria-label="`Teks opsi ${i + 1}`"
                        class="min-h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2" />
                    <button type="button" :disabled="form.options.length <= 2"
                        class="min-h-10 rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100 disabled:opacity-40"
                        :aria-label="`Hapus opsi ${i + 1}`" @click="removeOption(i)">
                        &times;
                    </button>
                </div>
            </div>
            <button type="button" :disabled="form.options.length >= 6"
                class="mt-2 min-h-10 rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100 disabled:opacity-40"
                @click="addOption">
                Tambah opsi
            </button>
            <p v-for="message in optionErrors" :key="message" class="mt-1 text-sm text-red-700">{{ message }}</p>
        </fieldset>

        <div>
            <label for="qe-explanation" class="block text-sm font-medium">Penjelasan (opsional)</label>
            <textarea id="qe-explanation" v-model="form.explanation" rows="2"
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2"></textarea>
            <p v-if="errors.explanation" class="mt-1 text-sm text-red-700">{{ errors.explanation[0] }}</p>
        </div>

        <div class="flex gap-2">
            <button type="submit" :disabled="busy"
                class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ busy ? 'Menyimpan...' : 'Simpan soal' }}
            </button>
            <button type="button" :disabled="busy"
                class="min-h-10 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-100"
                @click="emit('cancel')">
                Batal
            </button>
        </div>
    </form>
</template>
