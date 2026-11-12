<script setup>
import { onMounted, ref } from 'vue';
import http from '../api/http';
import StudentHome from '../components/home/StudentHome.vue';
import TeacherHome from '../components/home/TeacherHome.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const home = ref(null);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/api/home');
        home.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="space-y-5">
        <PageHeader
            :title="`Halo, ${auth.user?.name ?? ''}`"
            :description="auth.isTeacher ? 'Ringkasan kelas Anda.' : 'Yang perlu Anda lakukan sekarang.'"
        />

        <div v-if="loading" class="space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-24 w-full" />
            <Skeleton class="h-24 w-full" />
            <span class="sr-only">Memuat beranda...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Beranda tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <TeacherHome v-else-if="home && home.role === 'teacher'" :home="home" />
        <StudentHome v-else-if="home" :home="home" />
    </section>
</template>