<script setup>
import BaseIcon from '../../components/ui/BaseIcon.vue';
import { ICONS } from '../../components/ui/icons';
import { ref } from 'vue';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import BaseInput from '../../components/ui/BaseInput.vue';
import BaseTextarea from '../../components/ui/BaseTextarea.vue';
import BaseSelect from '../../components/ui/BaseSelect.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import Badge from '../../components/ui/Badge.vue';
import InlineAlert from '../../components/ui/InlineAlert.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import Skeleton from '../../components/ui/Skeleton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';

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
const demoText = ref('');
const demoEmail = ref('abc');
const demoBody = ref('');
const demoRole = ref('student');
const crumbs = [{ label: 'Kelas', to: { name: 'courses' } }, { label: 'Matematika 7A' }];
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

        <section>
            <h2 class="text-lg font-semibold">Tombol</h2>
            <div class="mt-3 space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton>Primer</BaseButton>
                    <BaseButton variant="secondary">Sekunder</BaseButton>
                    <BaseButton variant="danger">Bahaya</BaseButton>
                    <BaseButton variant="ghost">Ghost</BaseButton>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton size="sm">Kecil</BaseButton>
                    <BaseButton :loading="true">Memproses</BaseButton>
                    <BaseButton :disabled="true">Nonaktif</BaseButton>
                    <BaseButton variant="secondary" :to="{ name: 'courses' }">Tautan ke Kelas</BaseButton>
                    <BaseButton variant="secondary"><BaseIcon name="plus" size="sm" />Dengan ikon</BaseButton>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Formulir</h2>
            <BaseCard class="mt-3">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Nama kelas" hint="Maksimal 100 karakter." required v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" :aria-describedby="describedby" :invalid="invalid" v-model="demoText" placeholder="Contoh: Matematika 7A" />
                    </FormField>
                    <FormField label="Email" error="Format email tidak valid." v-slot="{ id, describedby, invalid }">
                        <BaseInput :id="id" :aria-describedby="describedby" :invalid="invalid" v-model="demoEmail" type="email" />
                    </FormField>
                    <FormField label="Deskripsi" v-slot="{ id, describedby, invalid }">
                        <BaseTextarea :id="id" :aria-describedby="describedby" :invalid="invalid" v-model="demoBody" />
                    </FormField>
                    <FormField label="Peran" v-slot="{ id, describedby, invalid }">
                        <BaseSelect :id="id" :aria-describedby="describedby" :invalid="invalid" v-model="demoRole">
                            <option value="student">Siswa</option>
                            <option value="teacher">Guru</option>
                        </BaseSelect>
                    </FormField>
                </div>
            </BaseCard>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Kartu dan lencana</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <BaseCard>
                    <p class="font-semibold">Kartu dengan isi</p>
                    <p class="mt-1 text-sm text-slate-600">Kartu memakai sudut xl, batas, dan bayangan kecil.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Badge>Netral</Badge>
                        <Badge tone="brand">Merek</Badge>
                        <Badge tone="success">Dikuasai</Badge>
                        <Badge tone="warning">Berkembang</Badge>
                        <Badge tone="danger">Belum dikuasai</Badge>
                        <Badge tone="info">Info</Badge>
                    </div>
                </BaseCard>
                <BaseCard :padded="false">
                    <ul class="divide-y divide-slate-200 text-sm">
                        <li class="px-4 py-3">Baris pertama</li>
                        <li class="px-4 py-3">Baris kedua</li>
                        <li class="px-4 py-3">Baris ketiga</li>
                    </ul>
                </BaseCard>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Peringatan, keadaan kosong, dan memuat</h2>
            <div class="mt-3 space-y-3">
                <InlineAlert tone="info" title="Info">Teks informasi untuk pengguna.</InlineAlert>
                <InlineAlert tone="success" title="Berhasil">Perubahan sudah disimpan.</InlineAlert>
                <InlineAlert tone="warning" title="Perhatian">Kelas ini sudah diarsipkan.</InlineAlert>
                <InlineAlert tone="danger" title="Gagal">Tidak dapat terhubung ke server.</InlineAlert>
                <EmptyState title="Belum ada materi" description="Tambahkan materi pertama untuk kelas ini." icon="book-open">
                    <BaseButton><BaseIcon name="plus" size="sm" />Tambah materi</BaseButton>
                </EmptyState>
                <BaseCard>
                    <div class="space-y-2" role="status" aria-busy="true">
                        <Skeleton class="h-5 w-1/3" />
                        <Skeleton class="h-4 w-full" />
                        <Skeleton class="h-4 w-2/3" />
                        <span class="sr-only">Memuat...</span>
                    </div>
                </BaseCard>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Header halaman</h2>
            <BaseCard class="mt-3">
                <PageHeader title="Matematika 7A" description="Kelas pecahan dan perbandingan." :breadcrumbs="crumbs">
                    <template #actions>
                        <BaseButton variant="secondary" size="sm">Arsipkan</BaseButton>
                        <BaseButton size="sm"><BaseIcon name="plus" size="sm" />Materi baru</BaseButton>
                    </template>
                </PageHeader>
            </BaseCard>
        </section>
    </div>
</template>