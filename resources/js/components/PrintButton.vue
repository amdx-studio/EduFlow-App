<script setup>
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';

function print() {
    window.print();
}
</script>

<template>
    <BaseButton variant="secondary" class="print:hidden" aria-label="Cetak" @click="print">
        <BaseIcon name="printer" size="sm" /><span class="max-sm:sr-only">Cetak</span>
    </BaseButton>
</template>
