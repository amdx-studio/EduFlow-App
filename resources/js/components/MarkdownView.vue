<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';

const props = defineProps({
    source: { type: String, default: '' },
});

// HTML mentah dimatikan; hasil render tetap disanitasi (KT-02, bagian 10.5).
const md = new MarkdownIt({ html: false, linkify: true, breaks: false });

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

const html = computed(() => DOMPurify.sanitize(md.render(props.source ?? '')));
</script>

<template>
    <!-- Satu-satunya tempat v-html yang diizinkan -->
    <div class="md-view" v-html="html"></div>
</template>

<style scoped>
.md-view { line-height: 1.7; overflow-wrap: anywhere; }
.md-view :deep(h1) { font-size: 1.5rem; font-weight: 700; margin: 1.25rem 0 0.5rem; }
.md-view :deep(h2) { font-size: 1.25rem; font-weight: 700; margin: 1.25rem 0 0.5rem; }
.md-view :deep(h3) { font-size: 1.1rem; font-weight: 600; margin: 1rem 0 0.5rem; }
.md-view :deep(p) { margin: 0.6rem 0; }
.md-view :deep(ul) { list-style: disc; padding-left: 1.5rem; margin: 0.6rem 0; }
.md-view :deep(ol) { list-style: decimal; padding-left: 1.5rem; margin: 0.6rem 0; }
.md-view :deep(a) { color: var(--color-brand-700); text-decoration: underline; }
.md-view :deep(blockquote) { border-left: 4px solid var(--color-slate-300); padding-left: 0.75rem; color: var(--color-slate-600); margin: 0.75rem 0; }
.md-view :deep(code) { background: var(--color-slate-100); border-radius: 0.25rem; padding: 0.1rem 0.35rem; font-size: 0.9em; }
.md-view :deep(pre) { background: var(--color-slate-100); border-radius: 0.5rem; padding: 0.75rem; overflow-x: auto; margin: 0.75rem 0; }
.md-view :deep(pre code) { background: transparent; padding: 0; }
.md-view :deep(table) { border-collapse: collapse; margin: 0.75rem 0; }
.md-view :deep(th), .md-view :deep(td) { border: 1px solid var(--color-slate-300); padding: 0.35rem 0.6rem; }
.md-view :deep(hr) { border-color: var(--color-slate-200); margin: 1rem 0; }
</style>
