<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseTextarea from '../components/ui/BaseTextarea.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FormField from '../components/ui/FormField.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const concepts = ref([]);
const loading = ref(true);
const error = ref('');

const form = ref({ name: '', description: '' });
const formErrors = ref({});
const saving = ref(false);

const editingId = ref(null);
const editForm = ref({ name: '', description: '' });
const editErrors = ref({});
const savingEdit = ref(false);

const deleting = ref(null);
const deleteBusy = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/concepts`);
        concepts.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function add() {
    saving.value = true;
    formErrors.value = {};
    try {
        const { data } = await http.post(`/api/courses/${route.params.courseId}/concepts`, {
            name: form.value.name,
            description: form.value.description || null,
        });
        concepts.value = [...concepts.value, data.data].sort((a, b) => a.name.localeCompare(b.name, 'id'));
        form.value = { name: '', description: '' };
        toast.success('Konsep ditambahkan.');
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        saving.value = false;
    }
}

function startEdit(concept) {
    editingId.value = concept.id;
    editForm.value = { name: concept.name, description: concept.description ?? '' };
    editErrors.value = {};
}

function cancelEdit() {
    editingId.value = null;
    editErrors.value = {};
}

async function saveEdit(concept) {
    savingEdit.value = true;
    editErrors.value = {};
    try {
        const { data } = await http.put(`/api/concepts/${concept.id}`, {
            name: editForm.value.name,
            description: editForm.value.description || null,
        });
        concepts.value = concepts.value
            .map((c) => (c.id === concept.id ? data.data : c))
            .sort((a, b) => a.name.localeCompare(b.name, 'id'));
        editingId.value = null;
        toast.success('Konsep diperbarui.');
    } catch (e) {
        if (e.response?.status === 422) {
            editErrors.value = fieldErrors(e);
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        savingEdit.value = false;
    }
}

async function remove() {
    deleteBusy.value = true;
    try {
        await http.delete(`/api/concepts/${deleting.value.id}`);
        concepts.value = concepts.value.filter((c) => c.id !== deleting.value.id);
        toast.success('Konsep dihapus.');
    } catch (e) {
        // 409 bila konsep sudah dipakai soal, hasil, atau remedial: pesan datang dari API.
        toast.error(errorMessage(e));
    } finally {
        deleteBusy.value = false;
        deleting.value = null;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <section>
        <BaseCard>
            <form class="space-y-3" @submit.prevent="add">
                <h2 class="font-semibold">Tambah konsep</h2>
                <FormField label="Nama konsep" required :error="formErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseInput :id="id" v-model="form.name" maxlength="120" required
                        :aria-describedby="describedby" :invalid="invalid" />
                </FormField>
                <FormField label="Deskripsi (opsional)" :error="formErrors.description?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                    <BaseTextarea :id="id" v-model="form.description" :rows="2"
                        :aria-describedby="describedby" :invalid="invalid" />
                </FormField>
                <BaseButton type="submit" :loading="saving">
                    <BaseIcon v-if="!saving" name="plus" size="sm" />{{ saving ? 'Menyimpan...' : 'Tambah' }}
                </BaseButton>
            </form>
        </BaseCard>

        <div v-if="loading" class="mt-4 space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-16 w-full" />
            <span class="sr-only">Memuat konsep...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-4" tone="danger" title="Konsep tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <div v-else-if="concepts.length === 0" class="mt-4">
            <EmptyState
                title="Belum ada konsep"
                description="Tambahkan konsep pertama untuk mengelompokkan materi dan soal."
                icon="clipboard"
            />
        </div>

        <ul v-else class="mt-4 space-y-2">
            <li v-for="concept in concepts" :key="concept.id">
                <BaseCard>
                    <form v-if="editingId === concept.id" class="space-y-3" @submit.prevent="saveEdit(concept)">
                        <FormField label="Nama konsep" required :error="editErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseInput :id="id" v-model="editForm.name" maxlength="120" required
                                :aria-describedby="describedby" :invalid="invalid" />
                        </FormField>
                        <FormField label="Deskripsi" :error="editErrors.description?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                            <BaseTextarea :id="id" v-model="editForm.description" :rows="2"
                                :aria-describedby="describedby" :invalid="invalid" />
                        </FormField>
                        <div class="flex gap-2">
                            <BaseButton type="submit" :loading="savingEdit">{{ savingEdit ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                            <BaseButton variant="secondary" :disabled="savingEdit" @click="cancelEdit">Batal</BaseButton>
                        </div>
                    </form>

                    <div v-else class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ concept.name }}</p>
                            <p v-if="concept.description" class="mt-1 text-sm text-slate-600">{{ concept.description }}</p>
                        </div>
                        <div class="flex gap-2">
                            <BaseButton variant="secondary" size="sm" @click="startEdit(concept)">
                                <BaseIcon name="edit" size="sm" />Ubah
                            </BaseButton>
                            <BaseButton variant="danger-outline" size="sm" @click="deleting = concept">
                                <BaseIcon name="trash" size="sm" />Hapus
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            title="Hapus konsep?"
            :message="deleting ? `Konsep &quot;${deleting.name}&quot; akan dihapus. Konsep yang sudah dipakai soal tidak dapat dihapus.` : ''"
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>