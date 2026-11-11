<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { RouterLink } from 'vue-router';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    items: { type: Array, required: true },
    align: { type: String, default: 'right' },
    icon: { type: String, default: '' },
    collapseLabel: { type: Boolean, default: false },
});
const emit = defineEmits(['select']);

const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const menu = ref(null);
const menuId = `${useId()}-menu`;

function menuItems() {
    return menu.value ? Array.from(menu.value.querySelectorAll('[role="menuitem"]')) : [];
}

async function show(focus) {
    open.value = true;
    await nextTick();
    const list = menuItems();
    if (focus === 'first') {
        list[0]?.focus();
    } else if (focus === 'last') {
        list[list.length - 1]?.focus();
    }
}

function hide(returnFocus) {
    open.value = false;
    if (returnFocus) {
        trigger.value?.focus();
    }
}

function toggle() {
    if (open.value) {
        hide(false);
    } else {
        show(null);
    }
}

function onTriggerKey(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        show('first');
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        show('last');
    }
}

function onMenuKey(e) {
    const list = menuItems();
    const i = list.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        list[(i + 1) % list.length]?.focus();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        list[(i - 1 + list.length) % list.length]?.focus();
    } else if (e.key === 'Home') {
        e.preventDefault();
        list[0]?.focus();
    } else if (e.key === 'End') {
        e.preventDefault();
        list[list.length - 1]?.focus();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        hide(true);
    } else if (e.key === 'Tab') {
        hide(false);
    }
}

function choose(item) {
    emit('select', item.key ?? item.label);
    hide(true);
}

function onOutside(e) {
    if (open.value && root.value && !root.value.contains(e.target)) {
        hide(false);
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('pointerdown', onOutside);
    } else {
        document.removeEventListener('pointerdown', onOutside);
    }
});

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));

const itemClass = (item) =>
    `flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-100 focus:bg-slate-100 -outline-offset-2 ${
        item.danger ? 'text-danger-700' : 'text-slate-700'
    }`;
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            ref="trigger"
            type="button"
            class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            aria-haspopup="menu"
            :aria-label="collapseLabel ? label : undefined"
            :aria-expanded="open"
            :aria-controls="open ? menuId : undefined"
            @click="toggle"
            @keydown="onTriggerKey"
        >
            <BaseIcon v-if="icon" :name="icon" size="sm" />
            <span class="max-w-40 truncate" :class="{ 'hidden sm:inline': collapseLabel }">{{ label }}</span>
            <BaseIcon name="chevron-down" size="sm" />
        </button>
        <div
            v-if="open"
            :id="menuId"
            ref="menu"
            role="menu"
            class="absolute z-30 mt-1 min-w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            :class="align === 'right' ? 'right-0' : 'left-0'"
            @keydown="onMenuKey"
        >
            <template v-for="item in props.items" :key="item.key ?? item.label">
                <RouterLink v-if="item.to" :to="item.to" role="menuitem" tabindex="-1" :class="itemClass(item)" @click="choose(item)">
                    <BaseIcon v-if="item.icon" :name="item.icon" size="sm" />
                    {{ item.label }}
                </RouterLink>
                <button v-else type="button" role="menuitem" tabindex="-1" :class="itemClass(item)" @click="choose(item)">
                    <BaseIcon v-if="item.icon" :name="item.icon" size="sm" />
                    {{ item.label }}
                </button>
            </template>
        </div>
    </div>
</template>