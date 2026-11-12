<script setup>
import { RouterLink } from 'vue-router';
import Badge from '../ui/Badge.vue';
import BaseButton from '../ui/BaseButton.vue';
import BaseCard from '../ui/BaseCard.vue';
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
            :description="home.courses_count === 0 ? 'Buat kelas pertama Anda untuk mulai menyusun materi dan kuis.' : 'Buat kelas baru dari halaman Daftar kelas.'"
            icon="book-open"
        >
            <BaseButton :to="{ name: 'courses' }">Buka daftar kelas</BaseButton>
        </EmptyState>

        <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li v-for="course in home.courses" :key="course.id">
                <BaseCard>
                    <RouterLink :to="`/courses/${course.id}`" class="text-lg font-semibold text-brand-700 hover:underline">
                        {{ course.title }}
                    </RouterLink>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                        <span>{{ course.students_count }} siswa</span>
                        <span>{{ course.published_quizzes_count }} kuis terbit</span>
                        <Badge :tone="course.attention_count > 0 ? 'warning' : 'success'">
                            {{ course.attention_count > 0 ? `${course.attention_count} siswa perlu perhatian` : 'Tidak ada yang perlu perhatian' }}
                        </Badge>
                    </div>
                    <div class="mt-3">
                        <BaseButton variant="secondary" size="sm" :to="`/courses/${course.id}/dashboard`">Buka dashboard</BaseButton>
                    </div>
                </BaseCard>
            </li>
        </ul>
    </div>
</template>