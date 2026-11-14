<script setup>
import { RouterLink } from 'vue-router';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';

// RemediationItem (§6.14): garis kiri Anggur (belum selesai) atau hijau (selesai).
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
    <div class="border-l-4 py-1 pl-5" :class="item.status === 'completed' ? 'border-level-mastered-fill' : 'border-anggur-600'">
        <div class="flex flex-wrap items-center gap-2">
            <h3 v-if="showConcept" class="text-h3 font-semibold text-ink">{{ item.concept }}</h3>
            <Badge :tone="item.status === 'completed' ? 'success' : 'neutral'">
                {{ item.status === 'completed' ? 'Selesai' : 'Belum dipelajari' }}
            </Badge>
            <Badge v-if="item.source === 'teacher'" tone="remedial">Ditambahkan guru</Badge>
        </div>
        <p v-if="percent !== null && percent !== undefined" class="mt-1 text-caption text-kapur-500">
            Penguasaan saat rekomendasi: {{ Number(percent).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%
        </p>
        <p class="mt-2 text-body text-ink">{{ item.reason }}</p>
        <blockquote v-if="item.note" class="mt-2 border-l-2 border-kapur-300 pl-3 text-body text-kapur-600">{{ item.note }}</blockquote>
        <p class="mt-3 text-body">
            <RouterLink v-if="courseId" :to="`/courses/${courseId}/lessons/${item.lesson.id}`" class="text-small font-semibold text-samudra-600 underline hover:text-samudra-800">
                {{ item.lesson.title }}
            </RouterLink>
            <span v-else class="text-small font-semibold">{{ item.lesson.title }}</span>
        </p>
        <BaseButton v-if="canComplete && item.status !== 'completed'" size="sm" class="mt-4 max-md:w-full" :loading="busy" @click="$emit('complete', item)">
            {{ busy ? 'Menyimpan...' : 'Tandai selesai' }}
        </BaseButton>
    </div>
</template>
