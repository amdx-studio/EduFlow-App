<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import Badge from '../ui/Badge.vue';
import BaseButton from '../ui/BaseButton.vue';
import BaseIcon from '../ui/BaseIcon.vue';
import EmptyState from '../ui/EmptyState.vue';

const props = defineProps({
    home: { type: Object, required: true },
});

const hasAnything = computed(
    () => props.home.continue.length > 0
        || props.home.remediation.length > 0
        || props.home.quizzes.available.length > 0
        || props.home.quizzes.locked.length > 0,
);
const moreAvailable = computed(() => props.home.quizzes.available_total - props.home.quizzes.available.length);
const moreLocked = computed(() => props.home.quizzes.locked_total - props.home.quizzes.locked.length);

function kindLabel(kind) {
    return kind === 'reassessment' ? 'Asesmen ulang' : 'Asesmen';
}
</script>

<template>
    <div>
        <EmptyState
            v-if="home.courses_count === 0"
            as="h2"
            title="Anda belum bergabung ke kelas"
            description="Masukkan kode gabung dari guru untuk mulai belajar."
            icon="book-open"
        >
            <BaseButton :to="{ name: 'courses' }">Gabung dengan kode</BaseButton>
        </EmptyState>

        <EmptyState
            v-else-if="!hasAnything"
            as="h2"
            title="Tidak ada yang perlu dikerjakan"
            description="Remedial, kuis, dan percobaan yang berjalan akan muncul di sini."
            icon="check-circle"
        >
            <BaseButton variant="secondary" :to="{ name: 'courses' }">Lihat daftar kelas</BaseButton>
        </EmptyState>

        <div v-else class="space-y-12">
            <section v-if="home.continue.length">
                <h2 class="font-serif text-h2 font-medium">Lanjutkan</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    <li v-for="item in home.continue" :key="item.attempt_id" class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-body font-semibold">{{ item.quiz.title }}</p>
                            <p class="text-caption text-kapur-500">{{ item.course.title }}, percobaan {{ item.attempt_no }}</p>
                        </div>
                        <BaseButton class="max-md:w-full" :to="`/attempts/${item.attempt_id}`">Lanjutkan kuis</BaseButton>
                    </li>
                </ul>
            </section>

            <section v-if="home.remediation.length">
                <h2 class="font-serif text-h2 font-medium">Remedial yang belum selesai</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    <li v-for="item in home.remediation" :key="item.course.id" class="flex flex-wrap items-center justify-between gap-3 border-l-4 border-l-anggur-600 py-4 pl-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-body font-semibold">{{ item.course.title }}</span>
                            <Badge tone="remedial">{{ item.pending_count }} remedial</Badge>
                        </div>
                        <BaseButton variant="secondary" class="max-md:w-full" :to="`/courses/${item.course.id}/remediation`">Buka Remedial Saya</BaseButton>
                    </li>
                </ul>
            </section>

            <section v-if="home.quizzes.available.length">
                <h2 class="font-serif text-h2 font-medium">Kuis tersedia</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    <li v-for="quiz in home.quizzes.available" :key="quiz.id" class="py-4">
                        <RouterLink :to="`/courses/${quiz.course.id}/quizzes/${quiz.id}`" class="text-body font-semibold text-samudra-600 hover:underline">{{ quiz.title }}</RouterLink>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-kapur-600">
                            <span>{{ quiz.course.title }}</span>
                            <Badge :tone="quiz.kind === 'reassessment' ? 'remedial' : 'neutral'">{{ kindLabel(quiz.kind) }}</Badge>
                            <span>{{ quiz.attempts_used }} dari {{ quiz.max_attempts }} percobaan</span>
                        </div>
                    </li>
                </ul>
                <p v-if="moreAvailable > 0" class="mt-3 text-small text-kapur-600">dan {{ moreAvailable }} kuis lainnya (lihat di tab Kuis tiap kelas).</p>
            </section>

            <section v-if="home.quizzes.locked.length">
                <h2 class="font-serif text-h2 font-medium">Kuis terkunci</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    <li v-for="quiz in home.quizzes.locked" :key="quiz.id" class="py-4">
                        <RouterLink :to="`/courses/${quiz.course.id}/quizzes/${quiz.id}`" class="inline-flex items-center gap-2 text-body font-semibold text-samudra-600 hover:underline">
                            <BaseIcon name="lock" size="sm" />{{ quiz.title }}
                        </RouterLink>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-kapur-600">
                            <span>{{ quiz.course.title }}</span>
                            <span>{{ quiz.gate.reason }}</span>
                            <RouterLink v-if="quiz.gate.pending_count > 0" :to="`/courses/${quiz.course.id}/remediation`" class="font-semibold text-samudra-600 underline">
                                {{ quiz.gate.pending_count }} remedial belum selesai
                            </RouterLink>
                        </div>
                    </li>
                </ul>
                <p v-if="moreLocked > 0" class="mt-3 text-small text-kapur-600">dan {{ moreLocked }} kuis terkunci lainnya.</p>
            </section>
        </div>
    </div>
</template>
