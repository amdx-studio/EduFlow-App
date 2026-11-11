<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseSelect from '../components/ui/BaseSelect.vue';
import FormField from '../components/ui/FormField.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const courseId = route.params.courseId;

const form = ref({ title: '', kind: 'assessment', base_quiz_id: '', max_attempts: 1 });
const errors = ref({});
const saving = ref(false);

const baseOptions = ref([]);
const loadingBases = ref(true);
const basesFailed = ref(false);

const isReassessment = computed(() => form.value.kind === 'reassessment');

// Kuis dasar yang boleh dipilih: asesmen yang sudah terbit di kelas ini (API-24).
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
        <BaseButton variant="ghost" size="sm" :to="`/courses/${courseId}/quizzes`">
            <BaseIcon name="arrow-left" size="sm" />Daftar kuis
        </BaseButton>
        <h2 class="mt-2 text-lg font-semibold">Buat kuis</h2>

        <BaseCard class="mt-3 max-w-xl">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <FormField label="Judul" required :error="errors.title?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.title" maxlength="150" required
                        :aria-describedby="describedby" :invalid="invalid" />
                </FormField>

                <fieldset>
                    <legend class="block text-sm font-medium text-slate-900">Jenis kuis</legend>
                    <div class="mt-1 flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex min-h-10 items-center gap-2">
                            <input v-model="form.kind" type="radio" value="assessment" class="h-4 w-4 accent-brand-600" />
                            Asesmen
                        </label>
                        <label class="inline-flex min-h-10 items-center gap-2">
                            <input v-model="form.kind" type="radio" value="reassessment" class="h-4 w-4 accent-brand-600" />
                            Asesmen ulang
                        </label>
                    </div>
                    <p v-if="errors.kind" class="mt-1 text-sm text-danger-700" role="alert">{{ errors.kind[0] }}</p>
                </fieldset>

                <FormField v-if="isReassessment" label="Kuis dasar" :error="errors.base_quiz_id?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseSelect :id="id" v-model="form.base_quiz_id" :disabled="loadingBases"
                        :aria-describedby="describedby" :invalid="invalid">
                        <option value="">Pilih kuis dasar</option>
                        <option v-for="quiz in baseOptions" :key="quiz.id" :value="quiz.id">{{ quiz.title }}</option>
                    </BaseSelect>
                    <p v-if="loadingBases" class="mt-1 text-sm text-slate-600">Memuat daftar kuis...</p>
                    <p v-else-if="basesFailed" class="mt-1 flex flex-wrap items-center gap-2 text-sm text-danger-700">
                        Daftar kuis dasar gagal dimuat.
                        <BaseButton variant="secondary" size="sm" @click="loadBases">Coba lagi</BaseButton>
                    </p>
                    <p v-else-if="baseOptions.length === 0" class="mt-1 text-sm text-slate-600">
                        Belum ada asesmen yang terbit di kelas ini. Terbitkan kuis dasar lebih dulu.
                    </p>
                </FormField>

                <FormField label="Percobaan maksimal" required :error="errors.max_attempts?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <div class="w-28">
                        <BaseInput :id="id" v-model="form.max_attempts" type="number" min="1" max="65535" required
                            :aria-describedby="describedby" :invalid="invalid" />
                    </div>
                </FormField>

                <div class="flex gap-2">
                    <BaseButton type="submit" :loading="saving">{{ saving ? 'Menyimpan...' : 'Buat kuis' }}</BaseButton>
                    <BaseButton variant="secondary" :to="`/courses/${courseId}/quizzes`">Batal</BaseButton>
                </div>
            </form>
        </BaseCard>
    </section>
</template>