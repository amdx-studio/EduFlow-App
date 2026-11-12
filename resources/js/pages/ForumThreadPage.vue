<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import MarkdownView from '../components/MarkdownView.vue';
import PostForm from '../components/PostForm.vue';
import ThreadForm from '../components/ThreadForm.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { errorMessage, fieldErrors } from '../lib/errors';
import { formatDateTime } from '../lib/format';
import { forumErrorMessage, roleLabel } from '../lib/forum';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const course = inject('course', ref(null));
const archived = computed(() => course.value?.is_archived === true);

const thread = ref(null);
const loading = ref(true);
const error = ref('');
const notFound = ref(false);

const editing = ref(false);
const saving = ref(false);
const editErrors = ref({});

const replyForm = ref(null);
const replying = ref(false);
const replyError = ref('');

const editingPostId = ref(null);
const postSaving = ref(false);
const postError = ref('');

const pending = ref(null); // { kind: 'delete-thread' | 'delete-post' | 'pin' | 'lock', post? }
const pendingBusy = ref(false);

const courseId = computed(() => route.params.courseId);
const me = computed(() => auth.user?.id);

// Guru yang dapat melihat kelas ini adalah pemiliknya (BR-019).
const isThreadAuthor = computed(() => thread.value?.author?.id === me.value);
const canEditThread = computed(() => !archived.value && isThreadAuthor.value);
const canDeleteThread = computed(() => !archived.value && (isThreadAuthor.value || auth.isTeacher));
const canModerate = computed(() => !archived.value && auth.isTeacher);
const canReply = computed(() => !archived.value && thread.value !== null && !thread.value.is_locked);

const canEditPost = (post) => !archived.value && post.author?.id === me.value;
const canDeletePost = (post) => !archived.value && (post.author?.id === me.value || auth.isTeacher);

async function fetchThread() {
    const { data } = await http.get(`/api/threads/${route.params.threadId}`);
    thread.value = data.data;
}

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;
    editing.value = false;
    editingPostId.value = null;
    try {
        await fetchThread();
    } catch (e) {
        thread.value = null;
        if (e.response?.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
}

// Muat ulang tanpa menampilkan kerangka, dipakai setelah 409 agar status terkunci/terbaru sinkron.
async function refresh() {
    try {
        await fetchThread();
    } catch {
        // tampilan lama dipertahankan
    }
}

async function handleFailure(e) {
    toast.error(forumErrorMessage(e));
    if (e.response?.status === 404) {
        thread.value = null;
        notFound.value = true;
        return;
    }
    if (e.response?.status === 409) {
        await refresh();
    }
}

function mergeThread(updated) {
    thread.value = { ...thread.value, ...updated, posts: thread.value.posts };
}

async function saveThread(payload) {
    saving.value = true;
    editErrors.value = {};
    try {
        const { data } = await http.put(`/api/threads/${thread.value.id}`, payload);
        mergeThread(data.data);
        editing.value = false;
        toast.success('Utas diperbarui.');
    } catch (e) {
        if (e.response?.status === 422) {
            editErrors.value = fieldErrors(e);
        } else {
            await handleFailure(e);
        }
    } finally {
        saving.value = false;
    }
}

async function moderate(field, value, message) {
    try {
        const { data } = await http.put(`/api/threads/${thread.value.id}`, { [field]: value });
        mergeThread(data.data);
        toast.success(message);
    } catch (e) {
        await handleFailure(e);
    }
}

async function removeThread() {
    try {
        await http.delete(`/api/threads/${thread.value.id}`);
        toast.success('Utas dihapus.');
        router.push(`/courses/${courseId.value}/forum`);
    } catch (e) {
        // 409 bila penulis non-guru menghapus utas yang sudah dibalas orang lain.
        await handleFailure(e);
    }
}

async function createPost(body) {
    replying.value = true;
    replyError.value = '';
    try {
        const { data } = await http.post(`/api/threads/${thread.value.id}/posts`, { body });
        thread.value.posts.push(data.data);
        thread.value.posts_count += 1;
        replyForm.value?.reset();
        toast.success('Balasan terkirim.');
    } catch (e) {
        if (e.response?.status === 422) {
            replyError.value = fieldErrors(e).body?.[0] ?? '';
        } else {
            await handleFailure(e);
        }
    } finally {
        replying.value = false;
    }
}

async function savePost(post, body) {
    postSaving.value = true;
    postError.value = '';
    try {
        const { data } = await http.put(`/api/posts/${post.id}`, { body });
        thread.value.posts = thread.value.posts.map((p) => (p.id === post.id ? data.data : p));
        editingPostId.value = null;
        toast.success('Balasan diperbarui.');
    } catch (e) {
        if (e.response?.status === 422) {
            postError.value = fieldErrors(e).body?.[0] ?? '';
        } else {
            await handleFailure(e);
        }
    } finally {
        postSaving.value = false;
    }
}

async function removePost(post) {
    try {
        await http.delete(`/api/posts/${post.id}`);
        thread.value.posts = thread.value.posts.filter((p) => p.id !== post.id);
        thread.value.posts_count -= 1;
        toast.success('Balasan dihapus.');
    } catch (e) {
        await handleFailure(e);
    }
}

function startEditPost(post) {
    editingPostId.value = post.id;
    postError.value = '';
}

const pendingInfo = computed(() => {
    const kind = pending.value?.kind;
    if (kind === 'delete-thread') {
        return {
            title: 'Hapus utas?',
            message: auth.isTeacher
                ? 'Utas dan semua balasannya akan dihapus permanen.'
                : 'Utas dan semua balasannya akan dihapus permanen. Utas yang sudah dibalas orang lain tidak dapat dihapus.',
            label: 'Ya, hapus',
            variant: 'danger',
        };
    }
    if (kind === 'delete-post') {
        return { title: 'Hapus balasan?', message: 'Balasan ini akan dihapus permanen.', label: 'Ya, hapus', variant: 'danger' };
    }
    if (kind === 'pin') {
        return thread.value?.is_pinned
            ? { title: 'Lepas sematan?', message: 'Utas akan kembali ke urutan biasa.', label: 'Ya, lepas sematan', variant: 'primary' }
            : { title: 'Sematkan utas?', message: 'Utas akan tampil di bagian atas forum.', label: 'Ya, sematkan', variant: 'primary' };
    }
    if (kind === 'lock') {
        return thread.value?.is_locked
            ? { title: 'Buka kunci utas?', message: 'Anggota kelas dapat membalas lagi.', label: 'Ya, buka kunci', variant: 'primary' }
            : { title: 'Kunci utas?', message: 'Balasan baru tidak akan diterima dari siapa pun.', label: 'Ya, kunci', variant: 'primary' };
    }
    return { title: '', message: '', label: '', variant: 'danger' };
});

async function runPending() {
    const action = pending.value;
    if (!action) {
        return;
    }
    pendingBusy.value = true;
    try {
        if (action.kind === 'delete-thread') {
            await removeThread();
        } else if (action.kind === 'delete-post') {
            await removePost(action.post);
        } else if (action.kind === 'pin') {
            const next = !thread.value.is_pinned;
            await moderate('is_pinned', next, next ? 'Utas disematkan.' : 'Sematan dilepas.');
        } else if (action.kind === 'lock') {
            const next = !thread.value.is_locked;
            await moderate('is_locked', next, next ? 'Utas dikunci.' : 'Kunci dibuka.');
        }
    } finally {
        pendingBusy.value = false;
        pending.value = null;
    }
}

onMounted(load);
watch(() => route.params.threadId, load);
</script>

<template>
    <section>
        <RouterLink :to="`/courses/${courseId}/forum`" class="text-sm text-brand-700 hover:underline">
            &larr; Forum
        </RouterLink>

        <div v-if="loading" class="mt-3 space-y-2" role="status" aria-busy="true">
            <Skeleton class="h-6 w-1/2" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-2/3" />
            <span class="sr-only">Memuat utas...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-3" title="Utas tidak ditemukan." icon="clipboard">
            <BaseButton variant="secondary" :to="`/courses/${courseId}/forum`">Kembali ke forum</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-3">
            {{ error }}
            <BaseButton variant="secondary" size="sm" class="ml-2" @click="load">Coba lagi</BaseButton>
        </InlineAlert>

        <template v-else-if="thread">
            <InlineAlert v-if="archived" tone="info" class="mt-3">Kelas diarsipkan. Forum hanya dapat dibaca.</InlineAlert>

            <BaseCard class="mt-3">
                <ThreadForm
                    v-if="editing"
                    :initial="{ title: thread.title, body: thread.body }"
                    submit-label="Simpan"
                    :saving="saving"
                    :errors="editErrors"
                    @submit="saveThread"
                    @cancel="editing = false; editErrors = {}"
                />

                <article v-else>
                    <h2 class="text-xl font-semibold text-slate-900">{{ thread.title }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                        <span>{{ thread.author?.name }}</span>
                        <Badge :tone="thread.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(thread.author?.role) }}</Badge>
                        <span>{{ formatDateTime(thread.created_at) }}</span>
                        <Badge v-if="thread.is_pinned" tone="info">Disematkan</Badge>
                        <Badge v-if="thread.is_locked" tone="warning">Terkunci</Badge>
                    </div>

                    <div class="mt-4">
                        <MarkdownView :source="thread.body" />
                    </div>

                    <div v-if="canEditThread || canDeleteThread || canModerate" class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                        <BaseButton v-if="canEditThread" variant="secondary" size="sm" @click="editing = true">
                            <BaseIcon name="edit" size="sm" />Sunting
                        </BaseButton>
                        <BaseButton v-if="canModerate" variant="secondary" size="sm" @click="pending = { kind: 'pin' }">
                            {{ thread.is_pinned ? 'Lepas sematan' : 'Sematkan' }}
                        </BaseButton>
                        <BaseButton v-if="canModerate" variant="secondary" size="sm" @click="pending = { kind: 'lock' }">
                            {{ thread.is_locked ? 'Buka kunci' : 'Kunci' }}
                        </BaseButton>
                        <BaseButton v-if="canDeleteThread" variant="danger-outline" size="sm" @click="pending = { kind: 'delete-thread' }">
                            <BaseIcon name="trash" size="sm" />Hapus
                        </BaseButton>
                    </div>
                </article>
            </BaseCard>

            <section class="mt-6" aria-labelledby="posts-heading">
                <h3 id="posts-heading" class="text-lg font-semibold text-slate-900">Balasan ({{ thread.posts_count }})</h3>
                <p v-if="thread.posts.length === 0" class="mt-2 text-sm text-slate-600">Belum ada balasan.</p>

                <ul v-else class="mt-3 space-y-2">
                    <li v-for="post in thread.posts" :key="post.id">
                        <BaseCard>
                            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                                <span class="font-medium text-slate-900">{{ post.author?.name }}</span>
                                <Badge :tone="post.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(post.author?.role) }}</Badge>
                                <span>{{ formatDateTime(post.created_at) }}</span>
                                <span v-if="post.updated_at !== post.created_at">(diubah)</span>
                            </div>

                            <div v-if="editingPostId === post.id" class="mt-3">
                                <PostForm
                                    :initial="post.body"
                                    submit-label="Simpan"
                                    :saving="postSaving"
                                    :error="postError"
                                    cancellable
                                    @submit="(body) => savePost(post, body)"
                                    @cancel="editingPostId = null"
                                />
                            </div>
                            <template v-else>
                                <div class="mt-2">
                                    <MarkdownView :source="post.body" />
                                </div>
                                <div v-if="canEditPost(post) || canDeletePost(post)" class="mt-3 flex flex-wrap gap-2">
                                    <BaseButton v-if="canEditPost(post)" variant="secondary" size="sm" @click="startEditPost(post)">
                                        <BaseIcon name="edit" size="sm" />Sunting
                                    </BaseButton>
                                    <BaseButton v-if="canDeletePost(post)" variant="danger-outline" size="sm" @click="pending = { kind: 'delete-post', post }">
                                        <BaseIcon name="trash" size="sm" />Hapus
                                    </BaseButton>
                                </div>
                            </template>
                        </BaseCard>
                    </li>
                </ul>
            </section>

            <div class="mt-6">
                <InlineAlert v-if="thread.is_locked && !archived" tone="warning">
                    Utas ini terkunci. Balasan baru tidak diterima.
                </InlineAlert>
                <BaseCard v-else-if="canReply">
                    <h3 class="text-lg font-semibold text-slate-900">Tulis balasan</h3>
                    <div class="mt-3">
                        <PostForm ref="replyForm" :saving="replying" :error="replyError" @submit="createPost" />
                    </div>
                </BaseCard>
            </div>

            <ConfirmDialog
                :open="pending !== null"
                :title="pendingInfo.title"
                :message="pendingInfo.message"
                :confirm-label="pendingInfo.label"
                :variant="pendingInfo.variant"
                :busy="pendingBusy"
                @confirm="runPending"
                @cancel="pending = null" />
        </template>
    </section>
</template>
