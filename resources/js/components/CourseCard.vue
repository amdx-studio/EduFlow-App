<script setup>
import { RouterLink } from 'vue-router';
import { useToastStore } from '../stores/toast';

defineProps({
    course: { type: Object, required: true },
    isTeacher: { type: Boolean, default: false },
});

const toast = useToastStore();

async function copyCode(code) {
    try {
        await navigator.clipboard.writeText(code);
        toast.success('Kode gabung disalin.');
    } catch {
        toast.error('Gagal menyalin. Salin kode secara manual.');
    }
}
</script>

<template>
    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <RouterLink :to="`/courses/${course.id}`" class="text-lg font-semibold text-indigo-700 hover:underline">
                {{ course.title }}
            </RouterLink>
            <span v-if="course.is_archived" class="shrink-0 rounded bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Arsip</span>
        </div>

        <p v-if="course.description" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ course.description }}</p>

        <div v-if="isTeacher" class="mt-3 flex flex-wrap items-center gap-3 text-sm">
            <span class="text-slate-600">{{ course.students_count ?? 0 }} siswa</span>
            <span class="flex items-center gap-2">
                <span class="text-slate-600">Kode gabung:</span>
                <code class="rounded bg-slate-100 px-2 py-1 font-mono font-semibold tracking-wider">{{ course.join_code }}</code>
                <button type="button"
                    class="min-h-10 rounded-md border border-slate-300 px-3 py-1 text-xs hover:bg-slate-100"
                    @click="copyCode(course.join_code)">
                    Salin
                </button>
            </span>
        </div>
        <p v-else class="mt-3 text-sm text-slate-600">Guru: {{ course.teacher_name }}</p>
    </article>
</template>