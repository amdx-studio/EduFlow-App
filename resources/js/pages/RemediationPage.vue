<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import RemediationCard from '../components/RemediationCard.vue';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const items = ref([]);
const loading = ref(true);
const error = ref('');
const busyLesson = ref(null);

// Dikelompokkan per konsep; urutan mengikuti API-37 (konsep, lalu id).
const groups = computed(() => {
    const map = new Map();
    for (const item of items.value) {
        const key = item.concept_id ?? item.concept;
        if (!map.has(key)) {
            map.set(key, { key, concept: item.concept, items: [] });
        }
        map.get(key).items.push(item);
    }
    return [...map.values()];
});

const pendingCount = computed(() => items.value.filter((i) => i.status === 'assigned').length);

async function load(showLoading = true) {
    if (showLoading) {
        loading.value = true;
    }
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/remediation`);
        items.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function complete(item) {
    busyLesson.value = item.lesson.id;
    try {
        const { data } = await http.post(`/api/lessons/${item.lesson.id}/complete`);
        toast.success(data.data.assignments_completed > 0 ? 'Remedial ditandai selesai.' : 'Materi sudah tercatat selesai.');
        // KT-11: satu materi dapat menyelesaikan beberapa assignment, jadi muat ulang seluruh daftar.
        await load(false);
    } catch (e) {
        if (e.response?.status === 404) {
            toast.error('Materi tidak tersedia. Hubungi guru.');
        } else {
            toast.error(errorMessage(e));
        }
    } finally {
        busyLesson.value = null;
    }
}

onMounted(() => load());
watch(() => route.params.courseId, () => load());
</script>

<template>
    <section>
        <p v-if="loading" class="text-slate-600">Memuat remedial...</p>

        <div v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ error }}
            <button type="button" class="ml-2 underline" @click="load()">Coba lagi</button>
        </div>

        <div v-else-if="items.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">
            Belum ada remedial. Remedial muncul setelah Anda mengerjakan kuis dan ada konsep yang perlu diperkuat.
        </div>

        <div v-else class="space-y-5">
            <p class="text-sm text-slate-600">
                {{ pendingCount }} dari {{ items.length }} remedial belum selesai.
            </p>
            <div v-for="group in groups" :key="group.key" class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">{{ group.concept }}</h2>
                <ul class="mt-3 space-y-3">
                    <li v-for="item in group.items" :key="item.id">
                        <RemediationCard
                            :item="item"
                            :course-id="route.params.courseId"
                            :percent="item.mastery_percent"
                            :show-concept="false"
                            can-complete
                            :busy="busyLesson === item.lesson.id"
                            @complete="complete" />
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
