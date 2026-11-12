<script setup>
import { RouterLink } from 'vue-router';
import { formatDateTime } from '../lib/format';
import { roleLabel } from '../lib/forum';
import Badge from './ui/Badge.vue';
import BaseCard from './ui/BaseCard.vue';

defineProps({
    thread: { type: Object, required: true },
    courseId: { type: [String, Number], required: true },
});
</script>

<template>
    <BaseCard>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <RouterLink
                    :to="`/courses/${courseId}/forum/${thread.id}`"
                    class="font-semibold text-brand-700 hover:underline"
                >
                    {{ thread.title }}
                </RouterLink>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <span>{{ thread.author?.name }}</span>
                    <Badge :tone="thread.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(thread.author?.role) }}</Badge>
                    <span>{{ formatDateTime(thread.created_at) }}</span>
                    <Badge v-if="thread.is_pinned" tone="info">Disematkan</Badge>
                    <Badge v-if="thread.is_locked" tone="warning">Terkunci</Badge>
                </div>
            </div>
            <p class="text-sm text-slate-600">{{ thread.posts_count ?? 0 }} balasan</p>
        </div>
    </BaseCard>
</template>
