<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import RemediationCard from '../components/RemediationCard.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage } from '../lib/errors';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();

const items = ref([]);
const loading = ref(true);
const error = ref('');
const busyLesson = ref(null);
const reassessments = ref([]);

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

// Daftar asesmen ulang dari API-23. Gagal tidak boleh merusak halaman remedial.
async function loadReassessments() {
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/quizzes`);
        reassessments.value = data.data.filter((q) => q.kind === 'reassessment');
    } catch {
        reassessments.value = [];
    }
}

async function load(showLoading = true) {
    if (showLoading) {
        loading.value = true;
    }
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/remediation`);
        items.value = data.data;
        await loadReassessments();
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
        <div v-if="loading" class="space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-5 w-48" />
            <Skeleton class="h-40 w-full" />
            <span class="sr-only">Memuat remedial...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Remedial tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load()">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="items.length === 0"
            title="Belum ada remedial"
            description="Remedial muncul setelah Anda mengerjakan kuis dan ada konsep yang perlu diperkuat."
            icon="book-open"
        />

        <div v-else class="space-y-5">
            <p class="text-sm text-slate-600">
                {{ pendingCount }} dari {{ items.length }} remedial belum selesai.
            </p>
            <BaseCard v-for="group in groups" :key="group.key">
                <h2 class="text-lg font-semibold">{{ group.concept }}</h2>
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
            </BaseCard>
        </div>

        <BaseCard v-if="!loading && !error && reassessments.length" class="mt-5">
            <h2 class="text-lg font-semibold">Asesmen ulang</h2>
            <ul class="mt-3 space-y-3">
                <li v-for="quiz in reassessments" :key="quiz.id" class="rounded-lg border border-slate-200 p-4">
                    <RouterLink :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                        class="font-medium text-brand-700 hover:underline">
                        {{ quiz.title }}
                    </RouterLink>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge :tone="quiz.gate?.status === 'available' ? 'success' : 'warning'">
                            {{ quiz.gate?.status === 'available' ? 'Tersedia' : 'Terkunci' }}
                        </Badge>
                        <span v-if="quiz.gate?.status === 'locked'" class="text-xs text-slate-600">{{ quiz.gate.reason }}</span>
                        <span v-if="quiz.gate?.pending?.length" class="text-xs text-slate-600">
                            {{ quiz.gate.pending.length }} remedial belum selesai
                        </span>
                    </div>
                </li>
            </ul>
        </BaseCard>
    </section>
</template>