<script setup>
import { useAuthStore } from '../stores/auth';
import QuizBuilderPage from './QuizBuilderPage.vue';
import QuizDetailPage from './QuizDetailPage.vue';

const auth = useAuthStore();
</script>

<template>
    <QuizBuilderPage v-if="auth.isTeacher" />
    <QuizDetailPage v-else />
</template>
