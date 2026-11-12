<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import Badge from '../ui/Badge.vue';
import BaseButton from '../ui/BaseButton.vue';
import BaseCard from '../ui/BaseCard.vue';
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

        <div v-else class="space-y-6">
            <section v-if="home.continue.length">
                <h2 class="text-lg font-semibold">Lanjutkan</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="item in home.continue" :key="item.attempt_id">
                        <BaseCard>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ item.quiz.title }}</p>
                                    <p class="text-sm text-slate-600">{{ item.course.title }} &middot; percobaan ke-{{ item.attempt_no }}</p>
                                </div>
                                <BaseButton :to="`/attempts/${item.attempt_id}`">Lanjutkan kuis</BaseButton>
                            </div>
                        </BaseCard>
                    </li>
                </ul>
            </section>

            <section v-if="home.remediation.length">
                <h2 class="text-lg font-semibold">Remedial yang belum selesai</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="item in home.remediation" :key="item.course.id">
                        <BaseCard>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ item.course.title }}</span>
                                    <Badge tone="warning">{{ item.pending_count }} remedial</Badge>
                                </div>
                                <BaseButton variant="secondary" :to="`/courses/${item.course.id}/remediation`">Buka Remedial Saya</BaseButton>
                            </div>
                        </BaseCard>
                    </li>
                </ul>
            </section>

            <section v-if="home.quizzes.available.length">
                <h2 class="text-lg font-semibold">Kuis tersedia</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="quiz in home.quizzes.available" :key="quiz.id">
                        <BaseCard>
                            <RouterLink :to="`/courses/${quiz.course.id}/quizzes/${quiz.id}`" class="font-semibold text-brand-700 hover:underline">
                                {{ quiz.title }}
                            </RouterLink>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span>{{ quiz.course.title }}</span>
                                <Badge>{{ kindLabel(quiz.kind) }}</Badge>
                                <span>{{ quiz.attempts_used }} dari {{ quiz.max_attempts }} percobaan</span>
                            </div>
                        </BaseCard>
                    </li>
                </ul>
                <p v-if="moreAvailable > 0" class="mt-2 text-sm text-slate-600">dan {{ moreAvailable }} kuis lainnya (lihat di tab Kuis tiap kelas).</p>
            </section>

            <section v-if="home.quizzes.locked.length">
                <h2 class="text-lg font-semibold">Kuis terkunci</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="quiz in home.quizzes.locked" :key="quiz.id">
                        <BaseCard>
                            <RouterLink :to="`/courses/${quiz.course.id}/quizzes/${quiz.id}`" class="font-semibold text-brand-700 hover:underline">
                                {{ quiz.title }}
                            </RouterLink>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span>{{ quiz.course.title }}</span>
                                <Badge tone="warning">Terkunci</Badge>
                                <span>{{ quiz.gate.reason }}</span>
                                <RouterLink v-if="quiz.gate.pending_count > 0" :to="`/courses/${quiz.course.id}/remediation`"
                                    class="font-medium text-brand-700 underline">
                                    {{ quiz.gate.pending_count }} remedial belum selesai
                                </RouterLink>
                            </div>
                        </BaseCard>
                    </li>
                </ul>
                <p v-if="moreLocked > 0" class="mt-2 text-sm text-slate-600">dan {{ moreLocked }} kuis terkunci lainnya.</p>
            </section>
        </div>
    </div>
</template>