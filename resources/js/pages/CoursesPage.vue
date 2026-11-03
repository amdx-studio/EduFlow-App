<script setup>
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>

<template>
    <section>
        <h1 class="text-xl font-bold">Daftar kelas</h1>
        <p class="mt-2 text-slate-600">
            Halo, {{ auth.user?.name }}. Halaman ini sementara (placeholder); daftar kelas dibuat di Phase 4.
        </p>
    </section>
</template>
