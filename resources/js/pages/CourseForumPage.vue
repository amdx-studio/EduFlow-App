<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import ForumThreadRow from '../components/ForumThreadRow.vue';
import ThreadForm from '../components/ThreadForm.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { forumErrorMessage } from '../lib/forum';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

// Disediakan CourseLayout; kelas arsip = forum hanya dapat dibaca (BR-019).
const course = inject('course', ref(null));
const archived = computed(() => course.value?.is_archived === true);

const pinned = ref([]);
const threads = ref([]);
const hasMore = ref(false);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');

const showForm = ref(false);
const creating = ref(false);
const formErrors = ref({});

const listUrl = () => `/api/courses/${route.params.courseId}/threads`;

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(listUrl());
        pinned.value = data.data.pinned;
        threads.value = data.data.threads;
        hasMore.value = data.data.has_more;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function loadMore() {
    const last = threads.value[threads.value.length - 1];
    if (!last) {
        return;
    }
    loadingMore.value = true;
    try {
        const { data } = await http.get(listUrl(), { params: { before_id: last.id } });
        const known = new Set(threads.value.map((t) => t.id));
        threads.value = [...threads.value, ...data.data.threads.filter((t) => !known.has(t.id))];
        pinned.value = data.data.pinned;
        hasMore.value = data.data.has_more;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loadingMore.value = false;
    }
}

function closeForm() {
    showForm.value = false;
    formErrors.value = {};
}

async function create(payload) {
    creating.value = true;
    formErrors.value = {};
    try {
        const { data } = await http.post(listUrl(), payload);
        toast.success('Utas dibuat.');
        router.push(`/courses/${route.params.courseId}/forum/${data.data.id}`);
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = fieldErrors(e);
        } else {
            // 409 (kelas arsip) dan 429 (throttle) ditampilkan sebagai toast.
            toast.error(forumErrorMessage(e));
        }
    } finally {
        creating.value = false;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);
</script>

<template>
    <section>
        <InlineAlert v-if="archived" tone="info" class="mb-4">Kelas diarsipkan. Forum hanya dapat dibaca.</InlineAlert>
        <div v-else-if="!showForm" class="mb-4">
            <BaseButton @click="showForm = true"><BaseIcon name="plus" size="sm" />Utas baru</BaseButton>
        </div>

        <BaseCard v-if="showForm && !archived" class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Utas baru</h2>
            <div class="mt-3">
                <ThreadForm submit-label="Buat utas" :saving="creating" :errors="formErrors" @submit="create" @cancel="closeForm" />
            </div>
        </BaseCard>

        <div v-if="loading" class="space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <span class="sr-only">Memuat forum...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" title="Forum tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="pinned.length === 0 && threads.length === 0"
            title="Belum ada utas"
            :description="archived ? 'Belum ada diskusi di kelas ini.' : 'Mulai diskusi dengan membuat utas pertama.'"
            icon="clipboard"
        />

        <template v-else>
            <section v-if="pinned.length" aria-labelledby="pinned-heading" class="mb-6">
                <h2 id="pinned-heading" class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-600">Disematkan</h2>
                <ul class="space-y-2">
                    <li v-for="thread in pinned" :key="thread.id">
                        <ForumThreadRow :thread="thread" :course-id="route.params.courseId" />
                    </li>
                </ul>
            </section>

            <section aria-labelledby="threads-heading">
                <h2 v-if="pinned.length" id="threads-heading" class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-600">Semua utas</h2>
                <ul class="space-y-2">
                    <li v-for="thread in threads" :key="thread.id">
                        <ForumThreadRow :thread="thread" :course-id="route.params.courseId" />
                    </li>
                </ul>
            </section>

            <div v-if="hasMore" class="mt-4 flex justify-center">
                <BaseButton variant="secondary" :loading="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Memuat...' : 'Muat lebih banyak' }}
                </BaseButton>
            </div>
        </template>
    </section>
</template>
