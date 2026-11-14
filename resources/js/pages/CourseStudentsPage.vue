<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import BaseButton from '../components/ui/BaseButton.vue';
import Avatar from '../components/domain/Avatar.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import CopyCodeChip from '../components/ui/CopyCodeChip.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { activeCourse } from '../composables/useShell';
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
    <section>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-h2 font-medium text-ink">Siswa</h2>
                <p v-if="!loading && !error" class="text-caption text-kapur-500">{{ students.length }} siswa</p>
            </div>
            <div v-if="activeCourse?.join_code" class="flex flex-wrap items-center gap-3">
                <p class="text-small text-kapur-600">Bagikan kode ini agar siswa dapat bergabung.</p>
                <CopyCodeChip :code="activeCourse.join_code" />
            </div>
        </div>

        <div v-if="loading" class="mt-4 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 5" :key="n" class="flex h-16 items-center gap-3 border-b border-line">
                <Skeleton class="h-9 w-9 !rounded-full" />
                <div class="flex-1 space-y-2"><Skeleton class="h-4 w-1/3" /><Skeleton class="h-3 w-1/2" /></div>
            </div>
            <span class="sr-only">Memuat daftar siswa...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-4" tone="danger" title="Daftar siswa tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="!students.length"
            class="mt-4"
            title="Belum ada siswa"
            description="Bagikan kode gabung kelas ini agar siswa dapat bergabung."
            icon="user"
        >
            <CopyCodeChip v-if="activeCourse?.join_code" :code="activeCourse.join_code" />
        </EmptyState>

        <ul v-else class="mt-4 border-t border-line">
            <li v-for="s in students" :key="s.id" class="border-b border-line">
                <RouterLink
                    :to="`/courses/${route.params.courseId}/students/${s.id}`"
                    class="flex min-h-16 items-center gap-4 py-3 transition-colors hover:bg-kapur-50"
                >
                    <Avatar :name="s.name" :size="40" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-h3 font-semibold text-ink">{{ s.name }}</span>
                        <span class="block truncate text-caption text-kapur-500">{{ s.email }}<span class="md:hidden"> · {{ formatDateTime(s.enrolled_at) }}</span></span>
                    </span>
                    <span class="hidden whitespace-nowrap text-small text-kapur-600 md:block">Bergabung {{ formatDateTime(s.enrolled_at) }}</span>
                    <span class="hidden text-small font-semibold text-samudra-600 md:block">Lihat detail</span>
                    <BaseIcon name="chevron-right" size="sm" class="text-kapur-400" />
                </RouterLink>
            </li>
        </ul>
    </section>
</template>
