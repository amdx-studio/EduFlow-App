<script setup>
import BaseButton from './ui/BaseButton.vue';
import BaseIcon from './ui/BaseIcon.vue';

function print() {
    window.print();
}
</script>

<template>
    <BaseButton variant="secondary" size="sm" class="print:hidden" @click="print">
        <BaseIcon name="printer" size="sm" />Cetak
    </BaseButton>
</template>