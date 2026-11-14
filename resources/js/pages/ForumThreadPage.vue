<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import MarkdownView from '../components/MarkdownView.vue';
import PostForm from '../components/PostForm.vue';
import ThreadForm from '../components/ThreadForm.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import Avatar from '../components/domain/Avatar.vue';
import Dropdown from '../components/ui/Dropdown.vue';
import PageHeader from '../components/ui/PageHeader.vue';
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

// Menu "Kelola utas" (§K-8): aksi merusak tidak menjadi tombol mandiri di header.
const manageItems = computed(() => {
    const items = [];
    if (canEditThread.value) {
        items.push({ key: 'edit', label: 'Sunting utas', icon: 'edit' });
    }
    if (canModerate.value) {
        items.push({ key: 'pin', label: thread.value?.is_pinned ? 'Lepas sematan' : 'Sematkan utas', icon: 'check' });
        items.push({ key: 'lock', label: thread.value?.is_locked ? 'Buka kunci utas' : 'Kunci utas', icon: 'lock' });
    }
    if (canDeleteThread.value) {
        items.push({ key: 'delete-thread', label: 'Hapus utas', icon: 'trash', danger: true });
    }
    return items;
});

function onManage(key) {
    if (key === 'edit') {
        editing.value = true;
        editErrors.value = {};
    } else {
        pending.value = { kind: key };
    }
}

onMounted(load);
watch(() => route.params.threadId, load);
</script>

<template>
    <section>
        <PageHeader
            :title="thread?.title ?? 'Utas'"
            :breadcrumbs="[{ label: 'Forum', to: `/courses/${courseId}/forum` }, { label: thread?.title ?? 'Utas' }]"
        >
            <template v-if="thread && !editing" #meta>
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-small text-kapur-600">
                    <span class="font-semibold text-ink">{{ thread.author?.name }}</span>
                    <Badge :tone="thread.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(thread.author?.role) }}</Badge>
                    <span>{{ formatDateTime(thread.created_at) }}</span>
                    <Badge v-if="thread.is_pinned" tone="info">Disematkan</Badge>
                    <Badge v-if="thread.is_locked" tone="warning">Terkunci</Badge>
                </div>
            </template>
            <template v-if="thread && !editing && manageItems.length" #actions>
                <Dropdown label="Kelola utas" icon="ellipsis" :items="manageItems" @select="onManage" />
            </template>
        </PageHeader>

        <div v-if="loading" class="mt-8 space-y-3" role="status" aria-busy="true">
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-2/3" />
            <Skeleton class="h-24 w-full" />
            <span class="sr-only">Memuat utas...</span>
        </div>

        <EmptyState v-else-if="notFound" class="mt-8" title="Utas tidak ditemukan" description="Utas ini tidak ada atau sudah dihapus." icon="clipboard">
            <BaseButton variant="secondary" :to="`/courses/${courseId}/forum`">Kembali ke forum</BaseButton>
        </EmptyState>

        <InlineAlert v-else-if="error" tone="danger" class="mt-8" title="Utas tidak dapat dimuat">
            {{ error }}
            <div class="mt-2"><BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton></div>
        </InlineAlert>

        <template v-else-if="thread">
            <InlineAlert v-if="archived" tone="info" class="mt-8">Kelas diarsipkan. Forum hanya dapat dibaca.</InlineAlert>

            <!-- Isi utas -->
            <div class="mt-8">
                <div v-if="editing" class="rounded-tile bg-well p-5 md:p-6">
                    <h2 class="font-serif text-h2 font-medium text-ink">Sunting utas</h2>
                    <div class="mt-5">
                        <ThreadForm
                            :initial="{ title: thread.title, body: thread.body }"
                            submit-label="Simpan"
                            :saving="saving"
                            :errors="editErrors"
                            @submit="saveThread"
                            @cancel="editing = false; editErrors = {}"
                        />
                    </div>
                </div>
                <article v-else class="max-w-170">
                    <MarkdownView :source="thread.body" variant="compact" />
                </article>
            </div>

            <!-- Balasan -->
            <section class="mt-12" aria-labelledby="posts-heading">
                <h2 id="posts-heading" class="font-serif text-h2 font-medium text-ink">Balasan</h2>
                <p class="text-caption text-kapur-500">{{ thread.posts_count }} balasan</p>
                <p v-if="thread.posts.length === 0" class="mt-4 text-body text-kapur-600">Belum ada balasan.</p>

                <ul v-else class="mt-4 border-t border-line">
                    <li v-for="post in thread.posts" :key="post.id" class="border-b border-line py-5">
                        <div class="flex items-start gap-4">
                            <Avatar :name="post.author?.name ?? ''" :size="36" class="mt-0.5 max-md:hidden" />
                            <div class="min-w-0 max-w-170 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-small text-kapur-600">
                                    <span class="font-semibold text-ink">{{ post.author?.name }}</span>
                                    <Badge :tone="post.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(post.author?.role) }}</Badge>
                                    <span>{{ formatDateTime(post.created_at) }}</span>
                                    <span v-if="post.updated_at !== post.created_at" class="text-kapur-500">(diubah)</span>
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
                                        <MarkdownView :source="post.body" variant="compact" />
                                    </div>
                                    <div v-if="canEditPost(post) || canDeletePost(post)" class="mt-3 flex flex-wrap gap-2">
                                        <BaseButton v-if="canEditPost(post)" variant="ghost" size="sm" :aria-label="`Sunting balasan ${post.author?.name}`" @click="startEditPost(post)">
                                            <BaseIcon name="edit" size="sm" />Sunting
                                        </BaseButton>
                                        <BaseButton v-if="canDeletePost(post)" variant="danger-outline" size="sm" :aria-label="`Hapus balasan ${post.author?.name}`" @click="pending = { kind: 'delete-post', post }">
                                            <BaseIcon name="trash" size="sm" />Hapus
                                        </BaseButton>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="mt-12">
                <InlineAlert v-if="thread.is_locked && !archived" tone="warning">
                    Utas ini terkunci. Balasan baru tidak diterima.
                </InlineAlert>
                <section v-else-if="canReply" class="max-w-170" aria-labelledby="reply-heading">
                    <h2 id="reply-heading" class="font-serif text-h2 font-medium text-ink">Tulis balasan</h2>
                    <div class="mt-5">
                        <PostForm ref="replyForm" :saving="replying" :error="replyError" @submit="createPost" />
                    </div>
                </section>
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
