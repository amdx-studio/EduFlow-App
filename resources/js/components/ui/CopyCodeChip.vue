<script setup>
import CopyButton from './CopyButton.vue';

// Kode gabung + tombol salin (§6.14). Kode ditulis monospace dengan jarak antarhuruf.
defineProps({
    code: { type: String, required: true },
});
</script>

<template>
    <span class="inline-flex items-center gap-2 rounded-control bg-well py-1 pl-3 pr-1">
        <code class="font-mono text-small font-medium tracking-[0.08em] text-ink">{{ code }}</code>
        <CopyButton :value="code" />
    </span>
</template>
