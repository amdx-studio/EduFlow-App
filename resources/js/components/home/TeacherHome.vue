<script setup>
import { RouterLink } from 'vue-router';
import GlyphLevel from '../domain/GlyphLevel.vue';
import Monogram from '../domain/Monogram.vue';
import BaseButton from '../ui/BaseButton.vue';
import BaseIcon from '../ui/BaseIcon.vue';
import EmptyState from '../ui/EmptyState.vue';

defineProps({
    home: { type: Object, required: true },
});
</script>

<template>
    <div>
        <EmptyState
            v-if="home.courses.length === 0"
            as="h2"
            :title="home.courses_count === 0 ? 'Belum ada kelas' : 'Semua kelas sudah diarsipkan'"
            :description="home.courses_count === 0 ? 'Buat kelas pertama untuk mulai menambahkan konsep, materi, dan kuis.' : 'Buat kelas baru dari halaman Kelas.'"
            icon="book-open"
        >
            <BaseButton :to="{ name: 'courses' }">Buat kelas</BaseButton>
        </EmptyState>

        <section v-else aria-labelledby="kelas-anda">
            <h2 id="kelas-anda" class="font-serif text-h2 font-medium">Kelas Anda</h2>
            <ul class="mt-5 grid gap-6 md:grid-cols-2">
                <li v-for="course in home.courses" :key="course.id">
                    <article class="flex h-full flex-col rounded-tile bg-well p-5 md:p-6">
                        <div class="flex items-center gap-4">
                            <Monogram :title="course.title" :size="48" />
                            <h3 class="min-w-0 font-serif text-[22px] leading-7 font-medium">
                                <RouterLink :to="`/courses/${course.id}`" class="text-ink hover:text-samudra-600">{{ course.title }}</RouterLink>
                            </h3>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-small text-kapur-600">
                            <span class="inline-flex items-center gap-2"><BaseIcon name="users" size="sm" />{{ course.students_count }} siswa</span>
                            <span class="inline-flex items-center gap-2"><BaseIcon name="clipboard" size="sm" />{{ course.published_quizzes_count }} kuis terbit</span>
                        </div>
                        <p v-if="course.attention_count > 0" class="mt-3 inline-flex items-center gap-2 text-small font-semibold text-level-not-mastered-text">
                            <GlyphLevel level="not_mastered" :size="16" />{{ course.attention_count }} siswa perlu perhatian
                        </p>
                        <p v-else class="mt-3 inline-flex items-center gap-2 text-small font-semibold text-level-mastered-text">
                            <GlyphLevel level="mastered" :size="16" />Tidak ada yang perlu perhatian
                        </p>
                        <div class="mt-auto pt-5">
                            <BaseButton variant="secondary" class="max-md:w-full" :to="`/courses/${course.id}/dashboard`">Buka dashboard</BaseButton>
                        </div>
                    </article>
                </li>
            </ul>
        </section>
    </div>
</template>
