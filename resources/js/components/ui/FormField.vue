<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    id: { type: String, default: '' },
});

const uid = useId();
const fieldId = computed(() => props.id || `${uid}-field`);
const hintId = computed(() => `${fieldId.value}-hint`);
const errorId = computed(() => `${fieldId.value}-error`);
const describedby = computed(() => {
    const ids = [props.hint ? hintId.value : '', props.error ? errorId.value : ''].filter(Boolean);
    return ids.length ? ids.join(' ') : undefined;
});
const invalid = computed(() => props.error !== '');
</script>

<template>
    <div>
        <label :for="fieldId" class="block text-small font-semibold text-ink">
            {{ label }}
            <span v-if="required" class="text-level-not-mastered-text" aria-hidden="true">*</span>
        </label>
        <p v-if="hint" :id="hintId" class="mt-1 text-caption text-kapur-500">{{ hint }}</p>
        <div class="mt-2">
            <slot :id="fieldId" :describedby="describedby" :invalid="invalid" />
        </div>
        <p v-if="error" :id="errorId" class="mt-2 text-caption text-level-not-mastered-text" role="alert">{{ error }}</p>
    </div>
</template>