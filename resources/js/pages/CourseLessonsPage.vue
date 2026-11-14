<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '../api/http';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ConceptChip from '../components/domain/ConceptChip.vue';
import Dropdown from '../components/ui/Dropdown.vue';
import BaseIcon from '../components/ui/BaseIcon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import InlineAlert from '../components/ui/InlineAlert.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import { RouterLink } from 'vue-router';
import { errorMessage } from '../lib/errors';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const lessons = ref([]);
const loading = ref(true);
const error = ref('');

const deleting = ref(null);
const deleteBusy = ref(false);
const deleteError = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get(`/api/courses/${route.params.courseId}/lessons`);
        lessons.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function remove() {
    deleteBusy.value = true;
    deleteError.value = '';
    try {
        await http.delete(`/api/lessons/${deleting.value.id}`);
        lessons.value = lessons.value.filter((l) => l.id !== deleting.value.id);
        toast.success('Materi dihapus.');
    } catch (e) {
        // 409 bila materi sudah dipakai sebagai remedial: pesan datang dari API, tampil sebagai alert (§7.5).
        deleteError.value = errorMessage(e);
    } finally {
        deleteBusy.value = false;
        deleting.value = null;
    }
}

onMounted(load);
watch(() => route.params.courseId, load);

const MAX_CHIPS = 2;

function menuItems(lesson) {
    return [
        { key: 'edit', label: 'Ubah', icon: 'edit', to: `/courses/${route.params.courseId}/lessons/${lesson.id}/edit` },
        { key: 'delete', label: 'Hapus', icon: 'trash', danger: true },
    ];
}

function onMenu(key, lesson) {
    if (key === 'delete') {
        deleteError.value = '';
        deleting.value = lesson;
    }
}
</script>

<template>
    <section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-serif text-h2 font-medium text-ink">Materi</h2>
            <BaseButton v-if="auth.isTeacher" :to="`/courses/${route.params.courseId}/lessons/new`">
                <BaseIcon name="plus" size="sm" />Tambah materi
            </BaseButton>
        </div>

        <InlineAlert v-if="deleteError" class="mt-4" tone="danger" title="Materi tidak dapat dihapus">{{ deleteError }}</InlineAlert>

        <div v-if="loading" class="mt-4 border-t border-line" role="status" aria-busy="true">
            <div v-for="n in 5" :key="n" class="flex h-16 items-center gap-3 border-b border-line">
                <Skeleton class="h-7 w-7 !rounded-full" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-4 w-1/2" />
                    <Skeleton class="h-3 w-1/4" />
                </div>
            </div>
            <span class="sr-only">Memuat materi...</span>
        </div>

        <InlineAlert v-else-if="error" class="mt-4" tone="danger" title="Materi tidak dapat dimuat">
            {{ error }}
            <div class="mt-2">
                <BaseButton variant="secondary" size="sm" @click="load">Coba lagi</BaseButton>
            </div>
        </InlineAlert>

        <EmptyState
            v-else-if="lessons.length === 0"
            class="mt-4"
            title="Belum ada materi"
            :description="auth.isTeacher ? 'Tulis materi pertama, lalu tautkan ke konsep agar dapat direkomendasikan sebagai remedial.' : 'Guru belum menerbitkan materi untuk kelas ini.'"
            icon="book-open"
        >
            <BaseButton v-if="auth.isTeacher" :to="`/courses/${route.params.courseId}/lessons/new`">
                <BaseIcon name="plus" size="sm" />Tambah materi
            </BaseButton>
        </EmptyState>

        <ul v-else class="mt-4 border-t border-line">
            <li
                v-for="lesson in lessons"
                :key="lesson.id"
                class="relative flex min-h-16 items-center gap-4 border-b border-line py-3 transition-colors hover:bg-kapur-50"
            >
                <span
                    v-if="!auth.isTeacher"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                    :class="lesson.completed ? 'bg-level-mastered-fill text-white' : 'border-2 border-kapur-400'"
                >
                    <BaseIcon v-if="lesson.completed" name="check" size="sm" />
                    <span v-if="lesson.completed" class="sr-only">Selesai</span>
                </span>

                <div class="min-w-0 flex-1">
                    <RouterLink
                        :to="`/courses/${route.params.courseId}/lessons/${lesson.id}`"
                        class="block truncate text-h3 font-semibold text-ink after:absolute after:inset-0 hover:text-samudra-700"
                    >
                        {{ lesson.title }}
                    </RouterLink>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <Badge :tone="lesson.kind === 'remedial' ? 'remedial' : 'neutral'">
                            {{ lesson.kind === 'remedial' ? 'Remedial' : 'Reguler' }}
                        </Badge>
                        <ConceptChip v-for="concept in (lesson.concepts ?? []).slice(0, MAX_CHIPS)" :key="concept.id" :name="concept.name" class="max-w-45 truncate" />
                        <span v-if="(lesson.concepts ?? []).length > MAX_CHIPS" class="text-caption font-semibold text-kapur-500">
                            +{{ lesson.concepts.length - MAX_CHIPS }}
                        </span>
                    </div>
                </div>

                <div v-if="auth.isTeacher" class="relative z-10 flex shrink-0 items-center gap-2">
                    <Badge :tone="lesson.is_published ? 'success' : 'neutral'">{{ lesson.is_published ? 'Terbit' : 'Draf' }}</Badge>
                    <div class="hidden items-center gap-1 md:flex">
                        <BaseButton variant="ghost" size="icon" :to="`/courses/${route.params.courseId}/lessons/${lesson.id}/edit`" :aria-label="`Ubah ${lesson.title}`">
                            <BaseIcon name="edit" size="sm" />
                        </BaseButton>
                        <BaseButton variant="ghost" size="icon" :aria-label="`Hapus ${lesson.title}`" @click="onMenu('delete', lesson)">
                            <BaseIcon name="trash" size="sm" />
                        </BaseButton>
                    </div>
                    <div class="md:hidden">
                        <Dropdown :label="`Aksi ${lesson.title}`" icon="ellipsis" collapse-label :items="menuItems(lesson)" @select="(key) => onMenu(key, lesson)" />
                    </div>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            :open="deleting !== null"
            :title="deleting ? `Hapus materi &quot;${deleting.title}&quot;?` : 'Hapus materi?'"
            message="Materi yang sudah dipakai sebagai remedial tidak dapat dihapus."
            confirm-label="Ya, hapus"
            :busy="deleteBusy"
            @confirm="remove"
            @cancel="deleting = null" />
    </section>
</template>
