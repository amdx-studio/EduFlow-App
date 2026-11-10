<script setup>
import BaseIcon from '../../components/ui/BaseIcon.vue';
import { ICONS } from '../../components/ui/icons';

// Nama kelas ditulis utuh agar terdeteksi Tailwind (tidak disusun dinamis).
const SWATCHES = {
    brand: ['bg-brand-50', 'bg-brand-100', 'bg-brand-200', 'bg-brand-300', 'bg-brand-400', 'bg-brand-500', 'bg-brand-600', 'bg-brand-700', 'bg-brand-800', 'bg-brand-900', 'bg-brand-950'],
    success: ['bg-success-50', 'bg-success-100', 'bg-success-200', 'bg-success-300', 'bg-success-400', 'bg-success-500', 'bg-success-600', 'bg-success-700', 'bg-success-800', 'bg-success-900', 'bg-success-950'],
    warning: ['bg-warning-50', 'bg-warning-100', 'bg-warning-200', 'bg-warning-300', 'bg-warning-400', 'bg-warning-500', 'bg-warning-600', 'bg-warning-700', 'bg-warning-800', 'bg-warning-900', 'bg-warning-950'],
    danger: ['bg-danger-50', 'bg-danger-100', 'bg-danger-200', 'bg-danger-300', 'bg-danger-400', 'bg-danger-500', 'bg-danger-600', 'bg-danger-700', 'bg-danger-800', 'bg-danger-900', 'bg-danger-950'],
    info: ['bg-info-50', 'bg-info-100', 'bg-info-200', 'bg-info-300', 'bg-info-400', 'bg-info-500', 'bg-info-600', 'bg-info-700', 'bg-info-800', 'bg-info-900', 'bg-info-950'],
};

const LABELS = { brand: 'Merek (brand)', success: 'Sukses', warning: 'Peringatan', danger: 'Bahaya', info: 'Info' };
const names = Object.keys(ICONS);
const shade = (cls) => cls.split('-').pop();
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-10 px-4 py-8 text-slate-900">
        <header>
            <h1 class="text-2xl font-semibold">Galeri komponen (hanya mode dev)</h1>
            <p class="mt-1 text-sm text-slate-600">Halaman ini tidak ikut build produksi.</p>
        </header>

        <section>
            <h2 class="text-lg font-semibold">Tipografi</h2>
            <div class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold">H1 text-2xl semibold</p>
                <p class="text-lg font-semibold">H2 text-lg semibold</p>
                <p class="text-base">Isi text-base: pembelajaran berbasis penguasaan konsep.</p>
                <p class="text-sm text-slate-600">Keterangan text-sm</p>
                <p class="text-xs text-slate-500">Metadata text-xs</p>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Warna (token)</h2>
            <div class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div v-for="(classes, palette) in SWATCHES" :key="palette">
                    <p class="text-sm font-medium">{{ LABELS[palette] }}</p>
                    <div class="mt-2 grid grid-cols-6 gap-2 sm:grid-cols-11">
                        <div v-for="cls in classes" :key="cls" class="text-center">
                            <div class="h-10 rounded-md ring-1 ring-slate-200" :class="cls"></div>
                            <span class="text-xs text-slate-600">{{ shade(cls) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Ikon ({{ names.length }})</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
                <div v-for="name in names" :key="name" class="flex items-center gap-2 text-sm text-slate-700">
                    <BaseIcon :name="name" size="sm" />
                    <BaseIcon :name="name" size="md" />
                    <BaseIcon :name="name" size="lg" />
                    <span class="truncate text-xs text-slate-500">{{ name }}</span>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Fokus (tekan Tab)</h2>
            <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <a href="#" class="text-brand-700 underline" @click.prevent>Tautan</a>
                <button type="button" class="min-h-10 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    Tombol merek
                </button>
                <input type="text" placeholder="Kolom isian" class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
        </section>
    </div>
</template>