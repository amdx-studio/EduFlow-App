<script setup>
import { RouterLink } from 'vue-router';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';

defineProps({
    item: { type: Object, required: true },
    courseId: { type: [Number, String], default: null },
    percent: { type: Number, default: null },
    showConcept: { type: Boolean, default: true },
    canComplete: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

defineEmits(['complete']);
</script>

<template>
    <div class="rounded-lg border border-slate-200 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span v-if="showConcept" class="font-medium">{{ item.concept }}</span>
            <Badge :tone="item.status === 'completed' ? 'success' : 'neutral'">
                {{ item.status === 'completed' ? 'Selesai' : 'Belum selesai' }}
            </Badge>
            <Badge v-if="item.source === 'teacher'" tone="brand">Ditambahkan guru</Badge>
        </div>
        <p v-if="percent !== null && percent !== undefined" class="mt-1 text-xs text-slate-600">
            Penguasaan saat rekomendasi: {{ Number(percent).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%
        </p>
        <p class="mt-2 text-sm text-slate-700">{{ item.reason }}</p>
        <p v-if="item.note" class="mt-1 text-sm text-slate-600">Catatan guru: {{ item.note }}</p>
        <p class="mt-2 text-sm">
            Materi:
            <RouterLink v-if="courseId" :to="`/courses/${courseId}/lessons/${item.lesson.id}`"
                class="font-medium text-brand-700 underline">
                {{ item.lesson.title }}
            </RouterLink>
            <span v-else class="font-medium">{{ item.lesson.title }}</span>
        </p>
        <BaseButton v-if="canComplete && item.status !== 'completed'" class="mt-3" :loading="busy"
            @click="$emit('complete', item)">
            {{ busy ? 'Menyimpan...' : 'Tandai selesai' }}
        </BaseButton>
    </div>
</template>