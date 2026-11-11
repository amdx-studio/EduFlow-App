<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';
import BaseInput from './ui/BaseInput.vue';
import BaseRadio from './ui/BaseRadio.vue';
import BaseSelect from './ui/BaseSelect.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';

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
        position: '',
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
        position: q.position,
        explanation: q.explanation ?? '',
        options: (q.options ?? []).map((o) => ({ body: o.body, is_correct: o.is_correct })),
    };
}

const form = ref(props.question ? fromQuestion(props.question) : blank());
watch(() => props.question, (q) => {
    form.value = q ? fromQuestion(q) : blank();
});

const correctIndex = computed(() => form.value.options.findIndex((o) => o.is_correct));

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
        ...(form.value.position !== '' && form.value.position !== null ? { position: Number(form.value.position) } : {}),
        options: form.value.options.map((o) => ({ body: o.body, is_correct: !!o.is_correct })),
    });
}
</script>

<template>
    <form class="space-y-4 rounded-xl border border-brand-200 bg-brand-50/40 p-4" @submit.prevent="submit">
        <h3 class="font-semibold">{{ question ? 'Ubah soal' : 'Tambah soal' }}</h3>

        <FormField label="Teks soal" required :error="errors.body?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.body" :rows="3" required
                :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <div class="flex flex-wrap gap-4">
            <FormField class="min-w-48 flex-1" label="Konsep" required :error="errors.concept_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseSelect :id="id" v-model="form.concept_id" required
                    :aria-describedby="describedby" :invalid="invalid">
                    <option value="" disabled>Pilih konsep</option>
                    <option v-for="concept in concepts" :key="concept.id" :value="concept.id">{{ concept.name }}</option>
                </BaseSelect>
                <p v-if="concepts.length === 0" class="mt-1 text-sm text-slate-600">
                    Belum ada konsep.
                    <RouterLink :to="`/courses/${route.params.courseId}/concepts`" class="font-medium text-brand-700 underline">Buat konsep</RouterLink>
                </p>
            </FormField>
            <FormField label="Poin" required :error="errors.points?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <div class="w-24">
                    <BaseInput :id="id" v-model="form.points" type="number" min="1" max="65535" required
                        :aria-describedby="describedby" :invalid="invalid" />
                </div>
            </FormField>
            <FormField class="max-w-48" label="Urutan" hint="Angka kecil tampil lebih dulu. Kosongkan untuk menaruh di akhir." :error="errors.position?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <div class="w-24">
                    <BaseInput :id="id" v-model="form.position" type="number" min="0"
                        :aria-describedby="describedby" :invalid="invalid" />
                </div>
            </FormField>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-slate-900">Opsi jawaban (pilih satu yang benar)</legend>
            <div class="mt-2 space-y-2">
                <div v-for="(option, i) in form.options" :key="i" class="flex items-center gap-2">
                    <BaseRadio :model-value="correctIndex" :value="i" name="qe-correct"
                        :aria-label="`Opsi ${i + 1} benar`" @update:model-value="setCorrect" />
                    <BaseInput v-model="option.body" required :aria-label="`Teks opsi ${i + 1}`" class="min-w-0 flex-1" />
                    <BaseButton variant="secondary" size="sm" :disabled="form.options.length <= 2"
                        :aria-label="`Hapus opsi ${i + 1}`" @click="removeOption(i)">
                        <BaseIcon name="x" size="sm" />
                    </BaseButton>
                </div>
            </div>
            <BaseButton class="mt-2" variant="secondary" size="sm" :disabled="form.options.length >= 6" @click="addOption">
                <BaseIcon name="plus" size="sm" />Tambah opsi
            </BaseButton>
            <p v-for="message in optionErrors" :key="message" class="mt-1 text-sm text-danger-700" role="alert">{{ message }}</p>
        </fieldset>

        <FormField label="Penjelasan (opsional)" :error="errors.explanation?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.explanation" :rows="2"
                :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <div class="flex gap-2">
            <BaseButton type="submit" :loading="busy">{{ busy ? 'Menyimpan...' : 'Simpan soal' }}</BaseButton>
            <BaseButton variant="secondary" :disabled="busy" @click="emit('cancel')">Batal</BaseButton>
        </div>
    </form>
</template>