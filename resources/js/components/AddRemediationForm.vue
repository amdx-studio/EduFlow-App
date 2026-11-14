<script setup>
import BaseButton from './ui/BaseButton.vue';
import BaseSelect from './ui/BaseSelect.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';

// Formulir "Tambah remedial" (§7.18). Dipakai di well sticky (≥ 1280 px) dan di dalam modal (di bawahnya).
// Keadaan formulir dimiliki halaman; komponen ini hanya menampilkan dan mengirim sinyal submit.
const form = defineModel({ type: Object, required: true });

const props = defineProps({
    attemptOptions: { type: Array, default: () => [] },
    lessons: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
});
defineEmits(['submit']);

function conceptsFor(attemptId) {
    return props.attemptOptions.find((q) => String(q.attempt_id) === String(attemptId))?.concepts ?? [];
}

function score(value) {
    return `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="$emit('submit')">
        <FormField label="Percobaan (terakhir per kuis)" required v-slot="{ id }">
            <BaseSelect :id="id" v-model="form.attemptId" required @change="form.conceptId = ''">
                <option value="" disabled>Pilih percobaan</option>
                <option v-for="q in attemptOptions" :key="q.attempt_id" :value="String(q.attempt_id)">{{ q.quiz.title }}</option>
            </BaseSelect>
        </FormField>
        <FormField label="Konsep" required :error="errors.concept_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseSelect :id="id" v-model="form.conceptId" required :disabled="!form.attemptId" :aria-describedby="describedby" :invalid="invalid">
                <option value="" disabled>Pilih konsep</option>
                <option v-for="c in conceptsFor(form.attemptId)" :key="c.concept_id" :value="String(c.concept_id)">{{ c.concept }} ({{ score(c.percent) }})</option>
            </BaseSelect>
        </FormField>
        <FormField label="Materi remedial" required :error="errors.lesson_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseSelect :id="id" v-model="form.lessonId" required :aria-describedby="describedby" :invalid="invalid">
                <option value="" disabled>{{ lessons.length ? 'Pilih materi' : 'Belum ada materi remedial terbit' }}</option>
                <option v-for="l in lessons" :key="l.id" :value="String(l.id)">{{ l.title }}</option>
            </BaseSelect>
        </FormField>
        <FormField label="Catatan (opsional)" :error="errors.note?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.note" :rows="3" maxlength="1000" :aria-describedby="describedby" :invalid="invalid" />
        </FormField>
        <BaseButton class="w-full" type="submit" :loading="saving" :disabled="!form.attemptId || !form.conceptId || !form.lessonId">
            {{ saving ? 'Menyimpan...' : 'Tambah remedial' }}
        </BaseButton>
    </form>
</template>
