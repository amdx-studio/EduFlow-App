<script setup>
import { RouterLink } from 'vue-router';
import Monogram from './domain/Monogram.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';
import CopyButton from './ui/CopyButton.vue';

// Tile kelas (§7.3): well, monogram, judul serif, meta, tombol "Buka kelas".
defineProps({
    course: { type: Object, required: true },
    isTeacher: { type: Boolean, default: false },
});
</script>

<template>
    <article class="flex h-full flex-col rounded-tile bg-well p-5 md:p-6">
        <div class="flex items-start gap-4">
            <Monogram :title="course.title" :size="48" />
            <div class="min-w-0 flex-1">
                <h3 class="font-serif text-[22px] leading-7 font-medium">
                    <RouterLink :to="`/courses/${course.id}`" class="text-ink hover:text-samudra-600">{{ course.title }}</RouterLink>
                </h3>
                <Badge v-if="course.is_archived" class="mt-1">Arsip</Badge>
            </div>
        </div>

        <p v-if="course.description" class="mt-3 line-clamp-2 text-body text-kapur-600">{{ course.description }}</p>

        <div v-if="isTeacher" class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-small text-kapur-600">
            <span class="inline-flex items-center gap-2"><BaseIcon name="users" size="sm" />{{ course.students_count ?? 0 }} siswa</span>
            <span class="inline-flex items-center gap-2">
                <code class="rounded-code bg-white px-2 py-1 font-mono font-medium tracking-[0.08em] text-ink">{{ course.join_code }}</code>
                <CopyButton :value="course.join_code" />
            </span>
        </div>
        <p v-else class="mt-4 text-small text-kapur-600">Guru: {{ course.teacher_name }}</p>

        <div class="mt-auto pt-5">
            <BaseButton variant="secondary" class="max-md:w-full" :to="`/courses/${course.id}`">Buka kelas</BaseButton>
        </div>
    </article>
</template>
