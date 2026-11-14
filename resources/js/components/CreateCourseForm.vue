<script setup>
import { computed, reactive, ref } from 'vue';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';
import BaseButton from './ui/BaseButton.vue';
import BaseInput from './ui/BaseInput.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';
import InlineAlert from './ui/InlineAlert.vue';

const emit = defineEmits(['created', 'cancel']);
const toast = useToastStore();

const form = reactive({
    title: '',
    description: '',
    mastery_threshold: 70,
    weak_threshold: 50,
    min_questions_per_concept: 2,
});
const hint = computed(() => `Konsep dengan skor ≥ ${form.mastery_threshold}% dikategorikan Dikuasai; ≥ ${form.weak_threshold}% Berkembang.`);
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        const { data } = await http.post('/api/courses', {
            title: form.title,
            description: form.description || null,
            mastery_threshold: Number(form.mastery_threshold),
            weak_threshold: Number(form.weak_threshold),
            min_questions_per_concept: Number(form.min_questions_per_concept),
        });
        toast.success('Kelas dibuat.');
        emit('created', data.data);
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) {
            message.value = errorMessage(e);
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="submit">
        <InlineAlert v-if="message" tone="danger">{{ message }}</InlineAlert>

        <FormField label="Judul kelas" required :error="errors.title?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseInput :id="id" v-model="form.title" maxlength="150" required :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <FormField label="Deskripsi (opsional)" :error="errors.description?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
            <BaseTextarea :id="id" v-model="form.description" :rows="3" :aria-describedby="describedby" :invalid="invalid" />
        </FormField>

        <details class="group rounded-control bg-well px-4 py-3" :open="Object.keys(errors).some((k) => k.includes('threshold') || k.includes('min_questions'))">
            <summary class="flex min-h-9 cursor-pointer list-none items-center justify-between text-small font-semibold [&::-webkit-details-marker]:hidden">
                Pengaturan penguasaan
                <span class="text-kapur-500 transition-transform duration-120 group-open:rotate-90" aria-hidden="true">›</span>
            </summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <FormField label="Ambang dikuasai (%)" :error="errors.mastery_threshold?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.mastery_threshold" type="number" min="1" max="100" :aria-describedby="describedby" :invalid="invalid" />
                </FormField>
                <FormField label="Ambang lemah (%)" :error="errors.weak_threshold?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.weak_threshold" type="number" min="1" :aria-describedby="describedby" :invalid="invalid" />
                </FormField>
                <FormField label="Minimal soal per konsep" :error="errors.min_questions_per_concept?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.min_questions_per_concept" type="number" min="1" :aria-describedby="describedby" :invalid="invalid" />
                </FormField>
            </div>
            <p class="mt-4 text-caption text-kapur-600">{{ hint }}</p>
            <p class="text-caption text-kapur-600">Perubahan ambang hanya memengaruhi percobaan berikutnya.</p>
        </details>

        <div class="flex justify-end gap-2">
            <BaseButton variant="secondary" :disabled="submitting" @click="emit('cancel')">Batal</BaseButton>
            <BaseButton type="submit" :loading="submitting">{{ submitting ? 'Menyimpan...' : 'Buat kelas' }}</BaseButton>
        </div>
    </form>
</template>
