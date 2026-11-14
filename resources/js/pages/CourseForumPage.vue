<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import ForumThreadRow from '../components/ForumThreadRow.vue';
import ThreadForm from '../components/ThreadForm.vue';
import BaseButton from '../components/ui/BaseButton.vue';
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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-h2 font-medium text-ink">Forum</h2>
                <p v-if="!loading && !error" class="text-caption text-kapur-500">{{ pinned.length + threads.length }} utas{{ hasMore ? ' dimuat' : '' }}</p>
            </div>
            <BaseButton v-if="!archived && !showForm" class="max-md:w-full" @click="showForm = true">
                <BaseIcon name="plus" size="sm" />Utas baru
            </BaseButton>
        </div>

        <InlineAlert v-if="archived" tone="info" class="mt-4">Kelas diarsipkan. Forum hanya dapat dibaca.</InlineAlert>

        <div v-if="showForm && !archived" class="mt-6 rounded-tile bg-well p-5 md:p-6">
            <h3 class="font-serif text-h2 font-medium text-ink">Utas baru</h3>
            <div class="mt-5">
                <ThreadForm submit-label="Buat utas" :saving="creating" :errors="formErrors" @submit="create" @cancel="closeForm" />
            </div>
        </div>

        <div v-if="loading" class="mt-4 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 4" :key="n" class="flex min-h-18 items-center gap-4 border-b border-line py-4">
                <Skeleton class="h-10 w-10 !rounded-full max-md:hidden" />
                <div class="flex-1 space-y-2"><Skeleton class="h-4 w-1/2" /><Skeleton class="h-3 w-1/3" /></div>
            </div>
            <span class="sr-only">Memuat forum...</span>
        </div>

        <InlineAlert v-else-if="error" tone="danger" class="mt-4" title="Forum tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="pinned.length === 0 && threads.length === 0"
            class="mt-4"
            title="Belum ada utas"
            :description="archived ? 'Belum ada diskusi di kelas ini.' : 'Mulai diskusi dengan membuat utas pertama.'"
            icon="clipboard"
        >
            <BaseButton v-if="!archived && !showForm" @click="showForm = true">
                <BaseIcon name="plus" size="sm" />Utas baru
            </BaseButton>
        </EmptyState>

        <template v-else>
            <section v-if="pinned.length" aria-labelledby="pinned-heading" class="mt-8">
                <h3 id="pinned-heading" class="text-h3 font-semibold text-ink">Disematkan</h3>
                <ul class="mt-3 border-t border-line">
                    <li v-for="thread in pinned" :key="thread.id" class="border-b border-line">
                        <ForumThreadRow :thread="thread" :course-id="route.params.courseId" />
                    </li>
                </ul>
            </section>

            <section aria-labelledby="threads-heading" class="mt-8">
                <h3 id="threads-heading" class="text-h3 font-semibold text-ink" :class="{ 'sr-only': !pinned.length }">Semua utas</h3>
                <ul class="border-t border-line" :class="pinned.length ? 'mt-3' : 'mt-0'">
                    <li v-for="thread in threads" :key="thread.id" class="border-b border-line">
                        <ForumThreadRow :thread="thread" :course-id="route.params.courseId" />
                    </li>
                </ul>
            </section>

            <div v-if="hasMore" class="mt-6 flex justify-center">
                <BaseButton variant="secondary" :loading="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Memuat...' : 'Muat lebih banyak' }}
                </BaseButton>
            </div>
        </template>
    </section>
</template>
