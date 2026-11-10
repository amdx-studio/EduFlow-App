<script setup>
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
</script>

<template>
    <section class="mx-auto max-w-lg py-10">
        <EmptyState as="h1" title="Halaman tidak ditemukan" description="Alamat yang Anda buka tidak ada atau sudah dipindahkan." icon="search">
            <BaseButton :to="{ name: 'courses' }">Ke daftar kelas</BaseButton>
        </EmptyState>
    </section>
</template>