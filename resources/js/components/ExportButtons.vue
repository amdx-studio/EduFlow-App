<script setup>
import { ref } from 'vue';
import { downloadFile } from '../lib/download';
import { useToastStore } from '../stores/toast';
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';

const props = defineProps({
    quizId: { type: [Number, String], required: true },
});

const toast = useToastStore();
const busy = ref('');

async function run(kind, fallback) {
    if (busy.value) {
        return;
    }
    busy.value = kind;
    try {
        await downloadFile(`/api/quizzes/${props.quizId}/export/${kind}`, fallback);
        toast.success('Berkas CSV diunduh.');
    } catch (e) {
        toast.error(e.message);
    } finally {
        busy.value = '';
    }
}
</script>

<template>
    <div class="flex flex-wrap gap-2 print:hidden">
        <BaseButton variant="secondary" size="sm" :loading="busy === 'attempts'" :disabled="busy !== ''" @click="run('attempts', 'percobaan.csv')">
            <BaseIcon name="download" size="sm" />Unduh CSV percobaan
        </BaseButton>
        <BaseButton variant="secondary" size="sm" :loading="busy === 'mastery'" :disabled="busy !== ''" @click="run('mastery', 'penguasaan.csv')">
            <BaseIcon name="download" size="sm" />Unduh CSV penguasaan
        </BaseButton>
    </div>
</template>