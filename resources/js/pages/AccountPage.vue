<script setup>
import { computed, ref } from 'vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import FormField from '../components/ui/FormField.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const NAME_MAX = 100;

const auth = useAuthStore();
const toast = useToastStore();

const roleLabel = computed(() => (auth.isTeacher ? 'Guru' : 'Siswa'));

function friendly(e) {
    if (e?.response?.status === 429) {
        return 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
    }
    return errorMessage(e);
}

// Nama
const name = ref(auth.user?.name ?? '');
const nameErrors = ref({});
const savingName = ref(false);
const nameChanged = computed(() => name.value.trim() !== '' && name.value.trim() !== (auth.user?.name ?? ''));

async function saveName() {
    savingName.value = true;
    nameErrors.value = {};
    try {
        await auth.updateProfile({ name: name.value.trim() });
        name.value = auth.user?.name ?? name.value;
        toast.success('Nama diperbarui.');
    } catch (e) {
        if (e.response?.status === 422) {
            nameErrors.value = fieldErrors(e);
        } else {
            toast.error(friendly(e));
        }
    } finally {
        savingName.value = false;
    }
}

// Password
const emptyPassword = () => ({ current_password: '', password: '', password_confirmation: '' });
const pw = ref(emptyPassword());
const pwErrors = ref({});
const savingPw = ref(false);
const canSavePw = computed(() => pw.value.current_password !== '' && pw.value.password !== '' && pw.value.password_confirmation !== '');

async function savePassword() {
    savingPw.value = true;
    pwErrors.value = {};
    try {
        await auth.changePassword({ ...pw.value });
        pw.value = emptyPassword();
        toast.success('Password diganti. Perangkat lain dikeluarkan.');
    } catch (e) {
        if (e.response?.status === 422) {
            pwErrors.value = fieldErrors(e);
        } else {
            toast.error(friendly(e));
        }
    } finally {
        savingPw.value = false;
    }
}
</script>

<template>
    <section class="space-y-5">
        <PageHeader title="Akun" description="Kelola nama dan password Anda." />

        <BaseCard>
            <form class="space-y-4" @submit.prevent="saveName">
                <h2 class="font-semibold text-slate-900">Profil</h2>
                <FormField label="Nama" required :hint="`${name.length}/${NAME_MAX} karakter`" :error="nameErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="name" :aria-describedby="describedby" :invalid="invalid" :maxlength="NAME_MAX" autocomplete="name" required />
                </FormField>
                <FormField label="Email" v-slot="{ id }">
                    <BaseInput :id="id" :model-value="auth.user?.email ?? ''" readonly disabled />
                </FormField>
                <div>
                    <p class="block text-sm font-medium text-slate-900">Peran</p>
                    <div class="mt-1"><Badge tone="brand">{{ roleLabel }}</Badge></div>
                </div>
                <BaseButton type="submit" :loading="savingName" :disabled="!nameChanged">
                    {{ savingName ? 'Menyimpan...' : 'Simpan nama' }}
                </BaseButton>
            </form>
        </BaseCard>

        <BaseCard>
            <form class="space-y-4" @submit.prevent="savePassword">
                <h2 class="font-semibold text-slate-900">Ganti password</h2>
                <FormField label="Password saat ini" required :error="pwErrors.current_password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="pw.current_password" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="current-password" required />
                </FormField>
                <FormField label="Password baru" hint="Minimal 8 karakter, berbeda dari password saat ini." required :error="pwErrors.password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="pw.password" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="new-password" required />
                </FormField>
                <FormField label="Konfirmasi password baru" required :error="pwErrors.password_confirmation?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="pw.password_confirmation" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="new-password" required />
                </FormField>
                <BaseButton type="submit" :loading="savingPw" :disabled="!canSavePw">
                    {{ savingPw ? 'Menyimpan...' : 'Ganti password' }}
                </BaseButton>
            </form>
        </BaseCard>
    </section>
</template>