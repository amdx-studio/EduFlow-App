<script setup>
import { computed, ref } from 'vue';
import MarkdownView from './MarkdownView.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseInput from './ui/BaseInput.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';

const TITLE_MAX = 150;
const BODY_MAX = 10000;

const props = defineProps({
    initial: { type: Object, default: () => ({ title: '', body: '' }) },
    submitLabel: { type: String, default: 'Simpan' },
    saving: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['submit', 'cancel']);

const form = ref({
    title: props.initial.title ?? '',
    body: props.initial.body ?? '',
});

const canSubmit = computed(() => form.value.title.trim() !== '' && form.value.body.trim() !== '');

function submit() {
    if (canSubmit.value) {
        emit('submit', { title: form.value.title, body: form.value.body });
    }
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <FormField
            label="Judul"
            :hint="`${form.title.length}/${TITLE_MAX} karakter`"
            :error="errors.title?.[0] ?? ''"
            required
            v-slot="{ id, describedby, invalid }"
        >
            <BaseInput :id="id" v-model="form.title" :aria-describedby="describedby" :invalid="invalid" :maxlength="TITLE_MAX" required />
        </FormField>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <FormField
                label="Isi (Markdown)"
                :hint="`${form.body.length}/${BODY_MAX} karakter`"
                :error="errors.body?.[0] ?? ''"
                required
                v-slot="{ id, describedby, invalid }"
            >
                <BaseTextarea
                    :id="id"
                    v-model="form.body"
                    :aria-describedby="describedby"
                    :invalid="invalid"
                    :rows="8"
                    :maxlength="BODY_MAX"
                    required
                    class="font-mono"
                />
            </FormField>
            <div>
                <p class="block text-small font-semibold text-ink">Pratinjau</p>
                <div class="mt-2 min-h-40 rounded-tile bg-well p-4">
                    <MarkdownView :source="form.body" variant="compact" />
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <BaseButton type="submit" :loading="saving" :disabled="!canSubmit">{{ saving ? 'Menyimpan...' : submitLabel }}</BaseButton>
            <BaseButton variant="secondary" :disabled="saving" @click="emit('cancel')">Batal</BaseButton>
        </div>
    </form>
</template>
