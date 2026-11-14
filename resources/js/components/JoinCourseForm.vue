<script setup>
import { ref } from 'vue';
import http from '../api/http';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';
import BaseButton from './ui/BaseButton.vue';
import BaseInput from './ui/BaseInput.vue';
import FormField from './ui/FormField.vue';

const emit = defineEmits(['joined']);
const toast = useToastStore();

const code = ref('');
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        const { data } = await http.post('/api/courses/join', { join_code: code.value });
        toast.success(`Anda bergabung di ${data.data.title}`);
        code.value = '';
        emit('joined', data.data);
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
    <form class="rounded-tile bg-well p-5 md:p-6" novalidate @submit.prevent="submit">
        <h2 class="font-serif text-h2 font-medium">Gabung kelas</h2>
        <FormField class="mt-4" label="Kode gabung" :error="errors.join_code?.[0] ?? message" v-slot="{ id, describedby, invalid }">
            <div class="flex flex-col gap-3 sm:flex-row">
                <BaseInput :id="id" v-model="code" maxlength="8" autocomplete="off" placeholder="Contoh: K7M2QX9A"
                    class="font-mono tracking-[0.08em] uppercase sm:max-w-xs"
                    :aria-describedby="describedby" :invalid="invalid" />
                <BaseButton type="submit" :loading="submitting" :disabled="!code.trim()">
                    {{ submitting ? 'Memproses...' : 'Gabung kelas' }}
                </BaseButton>
            </div>
        </FormField>
    </form>
</template>
