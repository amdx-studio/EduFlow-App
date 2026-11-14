<script setup>
import { RouterLink } from 'vue-router';
import { formatDateTime } from '../lib/format';
import { roleLabel } from '../lib/forum';
import Avatar from './domain/Avatar.vue';
import Badge from './ui/Badge.vue';

// Baris ledger utas (§6.4): tanpa kartu, hanya garis rambut dari daftar induk.
defineProps({
    thread: { type: Object, required: true },
    courseId: { type: [String, Number], required: true },
});
</script>

<template>
    <div class="flex min-h-18 items-start gap-4 py-4">
        <Avatar :name="thread.author?.name ?? ''" :size="40" class="mt-0.5 max-md:hidden" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <RouterLink
                    :to="`/courses/${courseId}/forum/${thread.id}`"
                    class="min-w-0 text-h3 font-semibold text-ink hover:text-samudra-700 hover:underline"
                >{{ thread.title }}</RouterLink>
                <Badge v-if="thread.is_pinned" tone="info">Disematkan</Badge>
                <Badge v-if="thread.is_locked" tone="warning">Terkunci</Badge>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-kapur-500">
                <span class="font-semibold text-kapur-600">{{ thread.author?.name }}</span>
                <Badge :tone="thread.author?.role === 'teacher' ? 'brand' : 'neutral'">{{ roleLabel(thread.author?.role) }}</Badge>
                <span>{{ formatDateTime(thread.created_at) }}</span>
            </div>
        </div>
        <p class="whitespace-nowrap text-small text-kapur-600 tabular-nums">{{ thread.posts_count ?? 0 }} balasan</p>
    </div>
</template>
