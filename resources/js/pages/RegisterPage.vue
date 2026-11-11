<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthShell from '../components/AuthShell.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import FormField from '../components/ui/FormField.vue';
import RadioGroup from '../components/ui/RadioGroup.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { errorMessage, fieldErrors } from '../lib/errors';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();

const form = reactive({ name: '', email: '', password: '', role: 'student', teacher_code: '' });
const errors = ref({});
const message = ref('');
const submitting = ref(false);
const roleOptions = [
    { value: 'student', label: 'Siswa' },
    { value: 'teacher', label: 'Guru' },
];

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    const payload = { name: form.name, email: form.email, password: form.password, role: form.role };
    if (form.role === 'teacher' && form.teacher_code !== '') {
        payload.teacher_code = form.teacher_code;
    }
    try {
        await auth.register(payload);
        toast.success('Akun berhasil dibuat.');
        await router.push('/courses');
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
    <AuthShell title="Buat akun" description="Daftar sebagai siswa atau guru.">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <InlineAlert v-if="message" tone="danger">{{ message }}</InlineAlert>

            <FormField label="Nama" :error="errors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.name" autocomplete="name" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <FormField label="Email" :error="errors.email?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.email" type="email" autocomplete="email" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <FormField label="Password" hint="Minimal 8 karakter." :error="errors.password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.password" type="password" autocomplete="new-password" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <RadioGroup v-model="form.role" legend="Peran" :options="roleOptions" :error="errors.role?.[0] ?? ''" />

            <FormField v-if="form.role === 'teacher'" label="Kode guru" :error="errors.teacher_code?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.teacher_code" autocomplete="off"
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <BaseButton type="submit" class="w-full" :loading="submitting">
                {{ submitting ? 'Memproses...' : 'Daftar' }}
            </BaseButton>
        </form>
    </AuthShell>
</template>