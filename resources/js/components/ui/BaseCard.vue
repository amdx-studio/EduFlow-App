<script setup>
defineProps({
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <div class="rounded-tile bg-well" :class="padded ? 'p-5 md:p-6' : ''">
        <slot />
    </div>
</template>