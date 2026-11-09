<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import { errorMessage } from '../lib/errors';
import { formatDateTime } from '../lib/format';

const route = useRoute();
const students = ref([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/students`);
        students.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <div>
        <p v-if="loading" class="text-slate-600">Memuat daftar siswa...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load">Coba lagi</button>
        </div>

        <div v-else-if="!students.length" class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            Belum ada siswa. Bagikan kode gabung kelas kepada siswa.
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Nama</th>
                        <th class="px-4 py-2 font-semibold">Email</th>
                        <th class="px-4 py-2 font-semibold">Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in students" :key="s.id" class="border-t border-slate-100">
                        <td class="px-4 py-2"><RouterLink :to="`/courses/${route.params.courseId}/students/${s.id}`" class="text-indigo-700 hover:underline">{{ s.name }}</RouterLink></td>
                        <td class="px-4 py-2">{{ s.email }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ formatDateTime(s.enrolled_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>