<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthShell from '../components/AuthShell.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import { useAuthStore } from '../stores/auth';
import { errorMessage, fieldErrors } from '../lib/errors';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.login({ email: form.email, password: form.password });
        const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
            ? route.query.redirect
            : '/courses';
        await router.push(target);
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
    <AuthShell title="Masuk" description="Masuk untuk melanjutkan belajar atau mengelola kelas.">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <InlineAlert v-if="message" tone="danger">{{ message }}</InlineAlert>

            <FormField label="Email" :error="errors.email?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.email" type="email" autocomplete="email" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <FormField label="Password" :error="errors.password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.password" type="password" autocomplete="current-password" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <BaseButton type="submit" class="w-full" :loading="submitting">
                {{ submitting ? 'Memproses...' : 'Masuk' }}
            </BaseButton>
        </form>
    </AuthShell>
</template>