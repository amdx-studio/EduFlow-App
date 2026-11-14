<script setup>
defineProps({ name: { type: String, required: true } });
</script>

<template>
    <span class="inline-flex items-center rounded-full bg-kapur-100 px-2.5 py-0.5 text-caption font-semibold text-kapur-600">{{ name }}</span>
</template>
