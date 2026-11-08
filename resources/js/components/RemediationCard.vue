<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    item: { type: Object, required: true },
    courseId: { type: [Number, String], default: null },
});
</script>

<template>
    <div class="rounded-md border border-slate-200 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium">{{ item.concept }}</span>
            <span class="rounded px-2 py-0.5 text-xs font-medium"
                :class="item.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'">
                {{ item.status === 'completed' ? 'Selesai' : 'Belum selesai' }}
            </span>
            <span v-if="item.source === 'teacher'"
                class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                Ditambahkan guru
            </span>
        </div>
        <p class="mt-2 text-sm text-slate-700">{{ item.reason }}</p>
        <p v-if="item.note" class="mt-1 text-sm text-slate-600">Catatan guru: {{ item.note }}</p>
        <p class="mt-2 text-sm">
            Materi:
            <RouterLink v-if="courseId" :to="`/courses/${courseId}/lessons/${item.lesson.id}`"
                class="text-indigo-700 underline">
                {{ item.lesson.title }}
            </RouterLink>
            <span v-else class="font-medium">{{ item.lesson.title }}</span>
        </p>
    </div>
</template>
