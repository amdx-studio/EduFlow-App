<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import FormField from '../components/ui/FormField.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const NAME_MAX = 100;

const router = useRouter();
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

// Keluar (§7.19): sama dengan menu pengguna.
const loggingOut = ref(false);
async function logout() {
    if (loggingOut.value) {
        return;
    }
    loggingOut.value = true;
    try {
        await auth.logout();
        toast.success('Anda telah keluar.');
        await router.push({ name: 'login' });
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <section>
        <PageHeader title="Akun" description="Kelola nama dan password Anda.">
            <template #actions>
                <BaseButton variant="secondary" :loading="loggingOut" @click="logout">
                    <BaseIcon name="log-out" size="sm" />Keluar
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mt-8 max-w-160 space-y-12 lg:mt-10">
            <section aria-labelledby="profil-title">
                <h2 id="profil-title" class="font-serif text-h2 font-medium text-ink">Profil</h2>
                <form class="mt-5 space-y-6" novalidate @submit.prevent="saveName">
                    <FormField label="Nama" required :hint="`${name.length}/${NAME_MAX} karakter`" :error="nameErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="name" :aria-describedby="describedby" :invalid="invalid" :maxlength="NAME_MAX" autocomplete="name" required />
                    </FormField>
                    <FormField label="Email" v-slot="{ id }">
                        <BaseInput :id="id" :model-value="auth.user?.email ?? ''" readonly disabled />
                    </FormField>
                    <div>
                        <p class="text-small font-semibold text-ink">Peran</p>
                        <div class="mt-2"><Badge :tone="auth.isTeacher ? 'brand' : 'neutral'">{{ roleLabel }}</Badge></div>
                        <p class="mt-2 text-caption text-kapur-500">Peran ditetapkan saat pendaftaran dan tidak dapat diubah.</p>
                    </div>
                    <div class="flex md:justify-end">
                        <BaseButton type="submit" class="max-md:w-full" :loading="savingName" :disabled="!nameChanged">
                            {{ savingName ? 'Menyimpan...' : 'Simpan nama' }}
                        </BaseButton>
                    </div>
                </form>
            </section>

            <section aria-labelledby="password-title" class="border-t border-line pt-12">
                <h2 id="password-title" class="font-serif text-h2 font-medium text-ink">Ganti password</h2>
                <form class="mt-5 space-y-6" novalidate @submit.prevent="savePassword">
                    <FormField label="Password saat ini" required :error="pwErrors.current_password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="pw.current_password" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="current-password" required />
                    </FormField>
                    <FormField label="Password baru" hint="Minimal 8 karakter, berbeda dari password saat ini." required :error="pwErrors.password?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="pw.password" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="new-password" required />
                    </FormField>
                    <FormField label="Konfirmasi password baru" required :error="pwErrors.password_confirmation?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="pw.password_confirmation" type="password" :aria-describedby="describedby" :invalid="invalid" autocomplete="new-password" required />
                    </FormField>
                    <div class="flex md:justify-end">
                        <BaseButton type="submit" class="max-md:w-full" :loading="savingPw" :disabled="!canSavePw">
                            {{ savingPw ? 'Menyimpan...' : 'Ganti password' }}
                        </BaseButton>
                    </div>
                </form>
            </section>
        </div>
    </section>
</template>
