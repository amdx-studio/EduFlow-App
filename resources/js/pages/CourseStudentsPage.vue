<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import BaseButton from '../components/ui/BaseButton.vue';
import DataTable from '../components/ui/DataTable.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
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
        <div v-if="loading" class="space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <span class="sr-only">Memuat daftar siswa...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Daftar siswa tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="!students.length"
            title="Belum ada siswa"
            description="Bagikan kode gabung kelas kepada siswa."
            icon="user"
        />

        <DataTable v-else label="Daftar siswa" caption="Siswa terdaftar di kelas ini">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Email</th>
                    <th scope="col">Bergabung</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in students" :key="s.id">
                    <td>
                        <RouterLink :to="`/courses/${route.params.courseId}/students/${s.id}`" class="font-medium text-brand-700 hover:underline">{{ s.name }}</RouterLink>
                    </td>
                    <td>{{ s.email }}</td>
                    <td class="whitespace-nowrap">{{ formatDateTime(s.enrolled_at) }}</td>
                </tr>
            </tbody>
        </DataTable>
    </div>
</template>