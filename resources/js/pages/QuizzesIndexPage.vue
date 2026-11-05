<script setup>
import { useAuthStore } from '../stores/auth';
import CourseQuizzesPage from './CourseQuizzesPage.vue';
import StudentQuizzesPage from './StudentQuizzesPage.vue';

const auth = useAuthStore();
</script>

<template>
    <CourseQuizzesPage v-if="auth.isTeacher" />
    <StudentQuizzesPage v-else />
</template>
