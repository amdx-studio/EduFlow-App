<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import http from '../api/http';
import RemediationCard from '../components/RemediationCard.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
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
        const n = data.data.assignments_completed;
        toast.success(n > 0 ? `Materi ditandai selesai. ${n} tugas remedial diperbarui.` : 'Materi ditandai selesai.');
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
        <h2 class="font-serif text-h2 font-medium text-ink">Remedial Saya</h2>
        <p v-if="!loading && !error && items.length" class="mt-1 text-body text-kapur-600">
            {{ pendingCount }} dari {{ items.length }} materi belum selesai.
        </p>

        <div v-if="loading" class="mt-6 space-y-8" role="status" aria-busy="true">
            <div v-for="n in 2" :key="n" class="space-y-3">
                <Skeleton class="h-6 w-40" />
                <Skeleton class="h-28 w-full" />
            </div>
            <span class="sr-only">Memuat remedial...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-6" tone="danger" title="Remedial tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load()">Coba lagi</BaseButton></div>
        </InlineAlert>

        <EmptyState
            v-else-if="items.length === 0"
            class="mt-6"
            title="Belum ada remedial"
            description="Remedial muncul setelah Anda mengerjakan kuis dasar dan ada konsep yang belum dikuasai."
            icon="book-open"
        >
            <BaseButton variant="secondary" :to="`/courses/${route.params.courseId}/quizzes`">Lihat kuis</BaseButton>
        </EmptyState>

        <div v-else class="mt-8 space-y-10">
            <section v-for="group in groups" :key="group.key" :aria-label="group.concept">
                <div class="flex items-baseline gap-3">
                    <h3 class="text-h3 font-semibold text-ink">{{ group.concept }}</h3>
                    <span class="text-caption text-kapur-500">{{ group.items.length }} materi</span>
                </div>
                <ul class="mt-4 space-y-5">
                    <li v-for="item in group.items" :key="item.id">
                        <RemediationCard
                            :item="item"
                            :course-id="route.params.courseId"
                            :show-concept="false"
                            can-complete
                            :busy="busyLesson === item.lesson.id"
                            @complete="complete" />
                    </li>
                </ul>
            </section>
        </div>

        <section v-if="!loading && !error && reassessments.length" class="mt-12" aria-label="Asesmen ulang">
            <h2 class="font-serif text-h2 font-medium text-ink">Asesmen ulang</h2>
            <ul class="mt-4 border-t border-line">
                <li v-for="quiz in reassessments" :key="quiz.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-4">
                    <div class="min-w-0">
                        <p class="text-h3 font-semibold text-ink">{{ quiz.title }}</p>
                        <p v-if="quiz.gate?.status === 'locked'" class="mt-1 flex items-center gap-2 text-small text-kapur-600">
                            <BaseIcon name="lock" size="sm" />{{ quiz.gate.reason }}
                            <span v-if="quiz.gate?.pending?.length">({{ quiz.gate.pending.length }} remedial belum selesai)</span>
                        </p>
                    </div>
                    <RouterLink
                        v-if="quiz.gate?.status === 'available'"
                        :to="`/courses/${route.params.courseId}/quizzes/${quiz.id}`"
                        class="text-small font-semibold text-samudra-600 underline hover:text-samudra-800"
                    >Buka kuis</RouterLink>
                    <Badge v-else tone="neutral">Terkunci</Badge>
                </li>
            </ul>
        </section>
    </section>
</template>
