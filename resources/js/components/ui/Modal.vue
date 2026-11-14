<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import BaseIcon from './BaseIcon.vue';

const open = defineModel({ type: Boolean, default: false });
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    size: { type: String, default: 'md' },
    busy: { type: Boolean, default: false },
    dismissible: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);

const uid = useId();
const titleId = `${uid}-title`;
const descId = `${uid}-desc`;
const panel = ref(null);
let previouslyFocused = null;
let previousOverflow = '';

const SIZES = { sm: 'max-w-120', md: 'max-w-150', lg: 'max-w-2xl' };
const FOCUSABLE =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusables() {
    if (!panel.value) {
        return [];
    }
    return Array.from(panel.value.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null);
}

function close() {
    if (props.busy) {
        return;
    }
    open.value = false;
    emit('close');
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (props.dismissible) {
            e.stopPropagation();
            close();
        }
        return;
    }
    if (e.key !== 'Tab') {
        return;
    }
    const items = focusables();
    if (items.length === 0) {
        e.preventDefault();
        panel.value?.focus();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    if (e.shiftKey && (active === first || active === panel.value)) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
    }
}

function release() {
    document.body.style.overflow = previousOverflow;
    previouslyFocused?.focus?.();
    previouslyFocused = null;
}

watch(
    open,
    async (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            await nextTick();
            const target = panel.value?.querySelector('[data-autofocus]') ?? focusables()[0] ?? panel.value;
            target?.focus();
        } else if (previouslyFocused !== null) {
            release();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (open.value) {
        release();
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-40 flex items-end justify-center bg-overlay md:items-center md:p-4"
            @mousedown.self="dismissible && close()"
            @keydown="onKeydown"
        >
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                :aria-describedby="description ? descId : undefined"
                tabindex="-1"
                class="w-full rounded-t-modal bg-white shadow-modal outline-none md:rounded-b-modal"
                :class="SIZES[size] ?? SIZES.md"
            >
                <div class="flex items-start justify-between gap-3 border-b border-line px-6 py-5">
                    <div class="min-w-0">
                        <h2 :id="titleId" class="font-serif text-h2 font-medium text-ink">{{ title }}</h2>
                        <p v-if="description" :id="descId" class="mt-1 text-sm text-kapur-600">{{ description }}</p>
                    </div>
                    <button
                        v-if="dismissible"
                        type="button"
                        class="-m-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-control text-kapur-500 hover:bg-kapur-100 disabled:opacity-50"
                        aria-label="Tutup"
                        :disabled="busy"
                        @click="close"
                    >
                        <BaseIcon name="x" />
                    </button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto px-6 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 border-t border-line px-6 py-4">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>