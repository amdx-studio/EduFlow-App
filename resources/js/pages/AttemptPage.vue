<script setup>
import { useRoute } from 'vue-router';
import QuizRunner from '../components/QuizRunner.vue';

const route = useRoute();
</script>

<template>
    <QuizRunner :key="route.params.attemptId" :attempt-id="route.params.attemptId" />
</template>
