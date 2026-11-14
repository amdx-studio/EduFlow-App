<script>
// Blok biasa (bukan setup): berjalan SEKALI per modul, bukan per instans komponen.
// Instans MarkdownIt dan hook DOMPurify (global) cukup dibuat satu kali (UT-05, OI-14).
import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';

// HTML mentah dimatikan; hasil render tetap disanitasi (KT-02, bagian 10.5).
const md = new MarkdownIt({ html: false, linkify: true, breaks: false });

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer');
    }
});
</script>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    source: { type: String, default: '' },
    // 'reading': gaya Bacaan serif untuk materi (§7.7). 'compact': isi forum dan pratinjau.
    variant: { type: String, default: 'reading' },
});

const html = computed(() => DOMPurify.sanitize(md.render(props.source ?? '')));
</script>

<template>
    <!-- Satu-satunya tempat v-html yang diizinkan -->
    <div class="md-view" :class="variant === 'compact' ? 'md-view--compact' : 'md-view--reading'" v-html="html"></div>
</template>

<style scoped>
.md-view { overflow-wrap: anywhere; color: var(--color-ink); }

/* Bacaan: Newsreader 19/32, mobile 18/30 */
.md-view--reading { font-family: var(--font-serif); font-size: 18px; line-height: 30px; }
@media (min-width: 768px) {
    .md-view--reading { font-size: 19px; line-height: 32px; }
}
/* Compact: isi forum dan pratinjau */
.md-view--compact { font-family: var(--font-sans); font-size: var(--text-body); line-height: var(--text-body--line-height); }

.md-view :deep(h1),
.md-view :deep(h2) { font-family: var(--font-serif); font-size: var(--text-h2); line-height: var(--text-h2--line-height); font-weight: 500; margin: 48px 0 12px; }
.md-view :deep(h3) { font-family: var(--font-sans); font-size: var(--text-h3); line-height: var(--text-h3--line-height); font-weight: 600; margin: 32px 0 8px; }
.md-view :deep(h1:first-child),
.md-view :deep(h2:first-child),
.md-view :deep(h3:first-child) { margin-top: 0; }
.md-view--compact :deep(h1),
.md-view--compact :deep(h2),
.md-view--compact :deep(h3) { font-family: var(--font-sans); font-size: var(--text-h3); line-height: var(--text-h3--line-height); font-weight: 600; margin: 20px 0 8px; }

.md-view :deep(p) { margin: 0 0 16px; }
.md-view :deep(p:last-child) { margin-bottom: 0; }
.md-view :deep(ul) { list-style: disc; padding-left: 24px; margin: 0 0 16px; }
.md-view :deep(ol) { list-style: decimal; padding-left: 24px; margin: 0 0 16px; }
.md-view :deep(li + li) { margin-top: 8px; }
.md-view :deep(a) { color: var(--color-samudra-600); text-decoration: underline; text-underline-offset: 2px; }
.md-view :deep(blockquote) { border-left: 3px solid var(--color-samudra-300); padding-left: 16px; color: var(--color-kapur-600); margin: 0 0 16px; }
.md-view :deep(code) { font-family: var(--font-mono); background: var(--color-kapur-100); border-radius: 6px; padding: 0.1em 0.35em; font-size: 0.9em; }
.md-view :deep(pre) { background: var(--color-kapur-50); border-radius: var(--radius-monogram); padding: 16px; overflow-x: auto; margin: 0 0 16px; font-size: var(--text-small); line-height: 22px; }
.md-view :deep(pre code) { background: transparent; padding: 0; font-size: inherit; }
.md-view :deep(img) { max-width: 100%; height: auto; border-radius: var(--radius-monogram); }
.md-view :deep(hr) { border: 0; border-top: 1px solid var(--color-line); margin: 32px 0; }

/* Tabel bergaya ledger: tanpa bingkai, garis rambut antarbaris, bisa digulir horizontal */
.md-view :deep(table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin: 0 0 16px; font-family: var(--font-sans); font-size: var(--text-small); line-height: var(--text-small--line-height); }
.md-view :deep(th) { text-align: left; font-weight: 600; color: var(--color-kapur-600); padding: 8px 16px 8px 0; border-bottom: 1px solid var(--color-kapur-300); white-space: nowrap; }
.md-view :deep(td) { padding: 12px 16px 12px 0; border-bottom: 1px solid var(--color-line); vertical-align: top; }
</style>
