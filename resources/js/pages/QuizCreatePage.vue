<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseSelect from '../components/ui/BaseSelect.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import RadioGroup from '../components/ui/RadioGroup.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;
const listPath = `/courses/${courseId}/quizzes`;

const form = ref({ title: '', kind: 'assessment', base_quiz_id: '', max_attempts: 1 });
const errors = ref({});
const saving = ref(false);
const kindOptions = [
    { value: 'assessment', label: 'Kuis dasar', description: 'Mengukur penguasaan awal. Dikerjakan lebih dulu oleh siswa.' },
    { value: 'reassessment', label: 'Asesmen ulang', description: 'Dikerjakan setelah remedial untuk melihat perubahan penguasaan. Tertaut ke satu kuis dasar.' },
];

const baseOptions = ref([]);
const loadingBases = ref(true);
const basesFailed = ref(false);

const isReassessment = computed(() => form.value.kind === 'reassessment');
const errorList = computed(() => Object.values(errors.value).flat());

// Kuis dasar yang boleh dipilih: kuis dasar yang sudah terbit di kelas ini (API-24).
async function loadBases() {
    loadingBases.value = true;
    basesFailed.value = false;
    try {
        const { data } = await http.get(`/api/courses/${courseId}/quizzes`);
        baseOptions.value = data.data.filter((q) => q.kind === 'assessment' && q.status === 'published');
    } catch {
        baseOptions.value = [];
        basesFailed.value = true;
    } finally {
        loadingBases.value = false;
    }
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            title: form.value.title,
            kind: form.value.kind,
            max_attempts: Number(form.value.max_attempts) || 1,
        };
        if (isReassessment.value) {
            payload.base_quiz_id = form.value.base_quiz_id || null;
        }
        const { data } = await http.post(`/api/courses/${courseId}/quizzes`, payload);
        toast.success('Kuis dibuat sebagai draf.');
        router.push(`/courses/${courseId}/quizzes/${data.data.id}`);
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = fieldErrors(e);
            await nextTick();
            document.querySelector('form [aria-invalid="true"]')?.focus();
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        saving.value = false;
    }
}

onMounted(loadBases);
</script>

<template>
    <section>
        <PageHeader
            title="Buat kuis"
            description="Kuis dibuat sebagai draf. Soal ditambahkan setelah ini."
            :breadcrumbs="[{ label: 'Kuis', to: listPath }, { label: 'Kuis baru' }]"
        />

        <form class="mt-8 max-w-160 space-y-6 lg:mt-10" novalidate @submit.prevent="save">
            <InlineAlert v-if="errorList.length >= 2" tone="danger" title="Periksa isian berikut">
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="message in errorList" :key="message">{{ message }}</li>
                </ul>
            </InlineAlert>

            <FormField label="Judul" required :error="errors.title?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.title" maxlength="150" required
                    :aria-describedby="describedby" :invalid="invalid" />
            </FormField>

            <RadioGroup v-model="form.kind" variant="card" legend="Jenis kuis" :options="kindOptions" :error="errors.kind?.[0] ?? ''" />

            <FormField v-if="isReassessment" label="Kuis dasar" required :error="errors.base_quiz_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseSelect :id="id" v-model="form.base_quiz_id" :disabled="loadingBases"
                    :aria-describedby="describedby" :invalid="invalid">
                    <option value="">Pilih kuis dasar</option>
                    <option v-for="quiz in baseOptions" :key="quiz.id" :value="quiz.id">{{ quiz.title }}</option>
                </BaseSelect>
                <p v-if="loadingBases" class="mt-2 text-caption text-kapur-500">Memuat daftar kuis dasar...</p>
                <p v-else-if="basesFailed" class="mt-2 flex flex-wrap items-center gap-2 text-caption text-level-not-mastered-text">
                    Daftar kuis dasar gagal dimuat.
                    <BaseButton variant="secondary" size="sm" @click="loadBases">Coba lagi</BaseButton>
                </p>
                <p v-else-if="baseOptions.length === 0" class="mt-2 text-caption text-kapur-500">
                    Belum ada kuis dasar yang terbit di kelas ini. Terbitkan kuis dasar lebih dulu.
                </p>
            </FormField>

            <FormField label="Percobaan maksimal" required hint="Berapa kali siswa boleh mengerjakan kuis ini." :error="errors.max_attempts?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <div class="w-28">
                    <BaseInput :id="id" v-model="form.max_attempts" type="number" min="1" max="65535" required
                        :aria-describedby="describedby" :invalid="invalid" />
                </div>
            </FormField>

            <div class="flex flex-col-reverse gap-3 md:flex-row md:justify-end">
                <BaseButton variant="secondary" :to="listPath">Batal</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ saving ? 'Menyimpan...' : 'Buat kuis' }}</BaseButton>
            </div>
        </form>
    </section>
</template>
