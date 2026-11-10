<script setup>
defineProps({
    label: { type: String, required: true },
    caption: { type: String, default: '' },
});
</script>

<template>
    <div
        class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm"
        role="region"
        :aria-label="label"
        tabindex="0"
    >
        <table
            class="min-w-full text-sm [&_td]:px-4 [&_td]:py-3 [&_th]:whitespace-nowrap [&_th]:bg-slate-50 [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-slate-700 [&_tbody_tr]:border-t [&_tbody_tr]:border-slate-200 [&_tbody_tr:hover]:bg-slate-50"
        >
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <slot />
        </table>
    </div>
</template>