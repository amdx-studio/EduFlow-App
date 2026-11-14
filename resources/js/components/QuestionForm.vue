<script setup>
import { computed, ref, useId, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';
import BaseInput from './ui/BaseInput.vue';
import BaseRadio from './ui/BaseRadio.vue';
import BaseSelect from './ui/BaseSelect.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';

// Isi form soal (§7.10): teks, konsep, poin, urutan, penjelasan, 2–6 opsi dengan tepat satu benar.
// Dipakai di dalam butir akordeon (ubah soal) dan di well "Soal baru".
const props = defineProps({
    question: { type: Object, default: null },
    concepts: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});
const emit = defineEmits(['save', 'cancel', 'delete']);

const route = useRoute();
const radioName = `qf-${useId()}`;

const MIN_OPTIONS = 2;
const MAX_OPTIONS = 6;
const letter = (i) => String.fromCharCode(65 + i);

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

// Form hanya disetel ulang bila isi soal di server berubah, sehingga menyimpan soal lain
// tidak menghapus suntingan yang belum disimpan di soal ini.
watch(
    () => JSON.stringify(props.question),
    () => {
        form.value = props.question ? fromQuestion(props.question) : blank();
    },
);

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

function cancel() {
    form.value = props.question ? fromQuestion(props.question) : blank();
    emit('cancel');
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
    <form class="space-y-6" novalidate @submit.prevent="submit">
        <FormField label="Teks soal" required :error="errors.body?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.body" :rows="3" required
                :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <div class="flex flex-wrap gap-x-4 gap-y-6">
            <FormField class="min-w-48 flex-1" label="Konsep" required :error="errors.concept_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseSelect :id="id" v-model="form.concept_id" required
                    :aria-describedby="describedby" :invalid="invalid">
                    <option value="" disabled>Pilih konsep</option>
                    <option v-for="concept in concepts" :key="concept.id" :value="concept.id">{{ concept.name }}</option>
                </BaseSelect>
                <p v-if="concepts.length === 0" class="mt-2 text-caption text-kapur-500">
                    Belum ada konsep.
                    <RouterLink :to="`/courses/${route.params.courseId}/concepts`" class="font-semibold text-samudra-600 underline underline-offset-2">Buat konsep</RouterLink>
                </p>
            </FormField>
            <FormField label="Poin" required :error="errors.points?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <div class="w-24">
                    <BaseInput :id="id" v-model="form.points" type="number" min="1" max="65535" required
                        :aria-describedby="describedby" :invalid="invalid" />
                </div>
            </FormField>
            <FormField class="max-w-56" label="Urutan (opsional)" hint="Angka kecil tampil lebih dulu. Kosongkan untuk menaruh di akhir." :error="errors.position?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <div class="w-24">
                    <BaseInput :id="id" v-model="form.position" type="number" min="0"
                        :aria-describedby="describedby" :invalid="invalid" />
                </div>
            </FormField>
        </div>

        <fieldset>
            <legend class="block text-small font-semibold text-ink">Opsi jawaban</legend>
            <p class="mt-1 text-caption text-kapur-500">Pilih satu jawaban yang benar. Minimal 2 dan maksimal 6 opsi.</p>
            <div class="mt-3 space-y-3">
                <div v-for="(option, i) in form.options" :key="i" class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-kapur-100 text-small font-semibold text-ink" aria-hidden="true">{{ letter(i) }}</span>
                    <BaseInput v-model="option.body" required :aria-label="`Teks opsi ${letter(i)}`" class="min-w-0 flex-1 basis-40" />
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-small text-ink">
                        <BaseRadio :model-value="correctIndex" :value="i" :name="radioName" @update:model-value="setCorrect" />
                        Jawaban benar<span class="sr-only"> untuk opsi {{ letter(i) }}</span>
                    </label>
                    <BaseButton variant="ghost" size="icon" class="!h-11 !w-11" :disabled="form.options.length <= MIN_OPTIONS"
                        :aria-label="`Hapus opsi ${letter(i)}`" @click="removeOption(i)">
                        <BaseIcon name="x" size="sm" />
                    </BaseButton>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <BaseButton variant="secondary" size="sm" :disabled="form.options.length >= MAX_OPTIONS" @click="addOption">
                    <BaseIcon name="plus" size="sm" />Tambah opsi
                </BaseButton>
                <span v-if="form.options.length >= MAX_OPTIONS" class="text-caption text-kapur-500">Maksimal 6 opsi</span>
            </div>
            <p v-for="message in optionErrors" :key="message" class="mt-2 text-caption text-level-not-mastered-text" role="alert">{{ message }}</p>
        </fieldset>

        <FormField label="Penjelasan (opsional)" hint="Ditampilkan setelah siswa mengirim kuis." :error="errors.explanation?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.explanation" :rows="2"
                :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <div class="flex flex-col-reverse gap-3 md:flex-row md:items-center md:justify-end">
            <BaseButton v-if="canDelete" class="md:mr-auto" variant="danger-outline" :disabled="busy" @click="emit('delete')">
                <BaseIcon name="trash" size="sm" />Hapus soal
            </BaseButton>
            <BaseButton variant="secondary" :disabled="busy" @click="cancel">Batal</BaseButton>
            <BaseButton type="submit" :loading="busy">{{ busy ? 'Menyimpan...' : 'Simpan soal' }}</BaseButton>
        </div>
    </form>
</template>
