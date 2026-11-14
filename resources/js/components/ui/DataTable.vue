<script setup>
// Ledger tabel (§6.4): hanya garis rambut horizontal, tanpa bingkai luar dan tanpa zebra.
defineProps({
    label: { type: String, required: true },
    caption: { type: String, default: '' },
});
</script>

<template>
    <div class="overflow-x-auto" role="region" :aria-label="label" tabindex="0">
        <table
            class="min-w-full text-small [&_td]:px-4 [&_td]:py-3.5 [&_th]:border-b [&_th]:border-line [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-caption [&_th]:font-semibold [&_th]:whitespace-nowrap [&_th]:text-kapur-600 [&_tbody_tr]:border-b [&_tbody_tr]:border-line [&_tbody_tr:hover]:bg-kapur-50 [&_td:first-child]:pl-0 [&_th:first-child]:pl-0"
        >
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <slot />
        </table>
    </div>
</template>
