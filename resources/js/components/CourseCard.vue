<script setup>
import { RouterLink } from 'vue-router';
import Badge from './ui/Badge.vue';
import BaseCard from './ui/BaseCard.vue';
import CopyButton from './ui/CopyButton.vue';

defineProps({
    course: { type: Object, required: true },
    isTeacher: { type: Boolean, default: false },
});
</script>

<template>
    <BaseCard class="transition-shadow duration-150 hover:shadow-md">
        <article>
            <div class="flex items-start justify-between gap-2">
                <RouterLink :to="`/courses/${course.id}`" class="text-lg font-semibold text-brand-700 hover:underline">
                    {{ course.title }}
                </RouterLink>
                <Badge v-if="course.is_archived" class="shrink-0">Arsip</Badge>
            </div>

            <p v-if="course.description" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ course.description }}</p>

            <div v-if="isTeacher" class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                <span class="text-slate-600">{{ course.students_count ?? 0 }} siswa</span>
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-slate-600">Kode gabung:</span>
                    <code class="rounded bg-slate-100 px-2 py-1 font-mono font-semibold tracking-wider text-slate-900">{{ course.join_code }}</code>
                    <CopyButton :value="course.join_code" />
                </span>
            </div>
            <p v-else class="mt-3 text-sm text-slate-600">Guru: {{ course.teacher_name }}</p>
        </article>
    </BaseCard>
</template>