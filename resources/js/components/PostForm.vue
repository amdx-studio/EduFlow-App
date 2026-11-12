<script setup>
import { computed, ref } from 'vue';
import MarkdownView from './MarkdownView.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseTextarea from './ui/BaseTextarea.vue';
import FormField from './ui/FormField.vue';

const BODY_MAX = 10000;

const props = defineProps({
    initial: { type: String, default: '' },
    submitLabel: { type: String, default: 'Kirim balasan' },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
    cancellable: { type: Boolean, default: false },
});
const emit = defineEmits(['submit', 'cancel']);

const body = ref(props.initial);
const showPreview = ref(false);

const canSubmit = computed(() => body.value.trim() !== '');

function submit() {
    if (canSubmit.value) {
        emit('submit', body.value);
    }
}

function reset() {
    body.value = '';
    showPreview.value = false;
}

defineExpose({ reset });
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <FormField
            label="Isi balasan (Markdown)"
            :hint="`${body.length}/${BODY_MAX} karakter`"
            :error="error"
            required
            v-slot="{ id, describedby, invalid }"
        >
            <BaseTextarea :id="id" v-model="body" :aria-describedby="describedby" :invalid="invalid" :rows="4" :maxlength="BODY_MAX" required />
        </FormField>

        <div v-if="showPreview" class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <MarkdownView :source="body" />
        </div>

        <div class="flex flex-wrap gap-2">
            <BaseButton type="submit" :loading="saving" :disabled="!canSubmit">{{ saving ? 'Menyimpan...' : submitLabel }}</BaseButton>
            <BaseButton variant="ghost" @click="showPreview = !showPreview">
                {{ showPreview ? 'Sembunyikan pratinjau' : 'Pratinjau' }}
            </BaseButton>
            <BaseButton v-if="cancellable" variant="secondary" :disabled="saving" @click="emit('cancel')">Batal</BaseButton>
        </div>
    </form>
</template>
