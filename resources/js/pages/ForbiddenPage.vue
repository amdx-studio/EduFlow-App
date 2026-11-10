<script setup>
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
</script>

<template>
    <section class="mx-auto max-w-lg py-10">
        <EmptyState as="h1" title="Tidak diizinkan" description="Peran Anda tidak memiliki akses ke halaman ini." icon="alert-triangle">
            <BaseButton :to="{ name: 'courses' }">Kembali ke daftar kelas</BaseButton>
        </EmptyState>
    </section>
</template>