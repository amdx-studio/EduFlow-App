<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import BaseInput from '../components/ui/BaseInput.vue';
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
const deleteError = ref(null); // { id, message }: tampil sebagai alert di bawah baris (§7.8)

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
    deleteError.value = null;
    try {
        await http.delete(`/api/concepts/${deleting.value.id}`);
        concepts.value = concepts.value.filter((c) => c.id !== deleting.value.id);
        toast.success('Konsep dihapus.');
    } catch (e) {
        // 409 bila konsep sudah dipakai soal, hasil, atau remedial: pesan datang dari API.
        deleteError.value = { id: deleting.value.id, message: errorMessage(e) };
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
        <h2 class="font-serif text-h2 font-medium text-ink">Konsep</h2>
        <p class="mt-1 max-w-prose text-body text-kapur-600">Konsep adalah satuan penguasaan. Soal kuis dan materi remedial ditautkan ke konsep.</p>

        <form class="mt-6 grid grid-cols-1 gap-4 rounded-tile bg-well p-5 md:grid-cols-[1fr_1fr_auto] md:items-start md:p-6" @submit.prevent="add">
            <FormField label="Nama konsep" required :error="formErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.name" maxlength="120" required :aria-describedby="describedby" :invalid="invalid" />
            </FormField>
            <FormField label="Deskripsi (opsional)" :error="formErrors.description?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                <BaseInput :id="id" v-model="form.description" :aria-describedby="describedby" :invalid="invalid" />
            </FormField>
            <div class="md:pt-7">
                <BaseButton class="w-full md:w-auto" type="submit" :loading="saving">
                    <BaseIcon v-if="!saving" name="plus" size="sm" />{{ saving ? 'Menyimpan...' : 'Tambah konsep' }}
                </BaseButton>
            </div>
        </form>

        <div v-if="loading" class="mt-6 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 3" :key="n" class="flex h-16 items-center border-b border-line">
                <div class="flex-1 space-y-2"><Skeleton class="h-4 w-1/3" /><Skeleton class="h-3 w-1/2" /></div>
            </div>
            <span class="sr-only">Memuat konsep...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-6" tone="danger" title="Konsep tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <EmptyState
            v-else-if="concepts.length === 0"
            class="mt-6"
            title="Belum ada konsep"
            description="Tambahkan konsep pertama, lalu tautkan ke soal dan materi remedial."
            icon="clipboard"
        />

        <ul v-else class="mt-6 border-t border-line">
            <li v-for="concept in concepts" :key="concept.id" class="border-b border-line">
                <form v-if="editingId === concept.id" class="grid grid-cols-1 gap-4 py-4 md:grid-cols-[1fr_1fr_auto] md:items-start" @submit.prevent="saveEdit(concept)">
                    <FormField label="Nama konsep" required :error="editErrors.name?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="editForm.name" maxlength="120" required :aria-describedby="describedby" :invalid="invalid" />
                    </FormField>
                    <FormField label="Deskripsi" :error="editErrors.description?.[0] ?? ''" v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" v-model="editForm.description" :aria-describedby="describedby" :invalid="invalid" />
                    </FormField>
                    <div class="flex gap-2 md:pt-7">
                        <BaseButton variant="secondary" :disabled="savingEdit" @click="cancelEdit">Batal</BaseButton>
                        <BaseButton type="submit" :loading="savingEdit">{{ savingEdit ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                    </div>
                </form>

                <div v-else class="flex min-h-16 items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-h3 font-semibold text-ink">{{ concept.name }}</p>
                        <p v-if="concept.description" class="text-caption text-kapur-500">{{ concept.description }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <BaseButton variant="ghost" size="icon" :aria-label="`Ubah ${concept.name}`" @click="startEdit(concept)">
                            <BaseIcon name="edit" size="sm" />
                        </BaseButton>
                        <BaseButton variant="ghost" size="icon" :aria-label="`Hapus ${concept.name}`" @click="deleteError = null; deleting = concept">
                            <BaseIcon name="trash" size="sm" />
                        </BaseButton>
                    </div>
                </div>

                <InlineAlert v-if="deleteError && deleteError.id === concept.id" class="mb-3" tone="danger" title="Konsep tidak dapat dihapus">{{ deleteError.message }}</InlineAlert>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            :title="deleting ? `Hapus konsep &quot;${deleting.name}&quot;?` : 'Hapus konsep?'"
            message="Konsep yang sudah dipakai soal, hasil, atau remedial tidak dapat dihapus."
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>
