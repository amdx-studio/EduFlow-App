<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
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
        <form class="rounded-lg border border-slate-200 bg-white p-4" @submit.prevent="add">
            <h2 class="font-semibold">Tambah konsep</h2>
            <div class="mt-3">
                <label for="concept-name" class="block text-sm font-medium">Nama konsep</label>
                <input id="concept-name" v-model="form.name" type="text" maxlength="120" required
                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                <p v-if="formErrors.name" class="mt-1 text-sm text-red-700">{{ formErrors.name[0] }}</p>
            </div>
            <div class="mt-3">
                <label for="concept-desc" class="block text-sm font-medium">Deskripsi (opsional)</label>
                <textarea id="concept-desc" v-model="form.description" rows="2"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                <p v-if="formErrors.description" class="mt-1 text-sm text-red-700">{{ formErrors.description[0] }}</p>
            </div>
            <button type="submit" :disabled="saving"
                class="mt-3 min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ saving ? 'Menyimpan...' : 'Tambah' }}
            </button>
        </form>

        <p v-if="loading" class="mt-4 text-slate-600">Memuat konsep...</p>

        <div v-else-if="error" class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="concepts.length === 0"
            class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            Belum ada konsep. Tambahkan konsep pertama untuk mengelompokkan materi dan soal.
        </div>

        <ul v-else class="mt-4 space-y-2">
            <li v-for="concept in concepts" :key="concept.id" class="rounded-lg border border-slate-200 bg-white p-4">
                <form v-if="editingId === concept.id" @submit.prevent="saveEdit(concept)">
                    <label :for="`edit-name-${concept.id}`" class="block text-sm font-medium">Nama konsep</label>
                    <input :id="`edit-name-${concept.id}`" v-model="editForm.name" type="text" maxlength="120" required
                        class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 py-2" />
                    <p v-if="editErrors.name" class="mt-1 text-sm text-red-700">{{ editErrors.name[0] }}</p>
                    <label :for="`edit-desc-${concept.id}`" class="mt-3 block text-sm font-medium">Deskripsi</label>
                    <textarea :id="`edit-desc-${concept.id}`" v-model="editForm.description" rows="2"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                    <div class="mt-3 flex gap-2">
                        <button type="submit" :disabled="savingEdit"
                            class="min-h-10 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ savingEdit ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                        <button type="button" :disabled="savingEdit"
                            class="min-h-10 rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100"
                            @click="cancelEdit">
                            Batal
                        </button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ concept.name }}</p>
                        <p v-if="concept.description" class="mt-1 text-sm text-slate-600">{{ concept.description }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button"
                            class="min-h-10 rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100"
                            @click="startEdit(concept)">
                            Ubah
                        </button>
                        <button type="button"
                            class="min-h-10 rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
                            @click="deleting = concept">
                            Hapus
                        </button>
                    </div>
                </div>
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
