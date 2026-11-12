# EduFlow Phase 13: Ekspor, Cetak, dan Akun (Laporan Final)

Tanggal: 13 November 2026. Cakupan: API-51 sampai 54, US-026 sampai US-030, BR-023, KT-27, KT-28, OI-18, OI-21, TC-053 sampai TC-058.

Tingkat kepastian: **Terbukti** (dari keluaran perintah), **Dilaporkan** (kata pengembang, tanpa bukti terlampir), **Dugaan** (belum diuji).

## 1. Ringkasan

Backend dan frontend Phase 13 selesai dan ter-merge ke `main` (`7f87444`). Seluruh pengujian otomatis lulus. Uji manual di browser (bagian 5) dilaporkan lulus untuk seluruh 8 skenario, termasuk OI-21 (dua browser) dan TC-058 (cetak). **Phase 13 dinyatakan tuntas.** Phase 13 tidak menambah migrasi.

## 2. Riwayat Commit

| Langkah | Commit | Isi |
|---|---|---|
| P13.1 | `9846a57` | `ExportService`, `ExportController`, dua rute ekspor |
| P13.2 | `a989e2b` | `ExportTest` (TC-053..055), rute ekspor masuk `AccessHardeningTest` |
| P13.3 | `ee2b910` | `ProfileController`, FormRequest profil dan password, throttle `password-change`, `ProfileTest` (TC-056, 057) |
| Merge backend | `51fa486` | `phase/13-ekspor-akun` ke `main` |
| P13.4 | `9ee94b8` | Halaman Akun, unduh CSV, gaya cetak |
| Merge UI | `d03171e` | `phase/13-ekspor-akun-ui` ke `main` (`--no-ff`) |
| Dokumen | `3a1e575` | Laporan final Phase 12 masuk repo |
| Perbaikan | `5d64468` | Label level CSV berbahasa Indonesia |
| Merge perbaikan | `7f87444` | `fix/csv-level-indonesia` ke `main` (`--no-ff`) |

Status git terakhir: `main` = `origin/main` = `7f87444`. Cabang `fix/csv-level-indonesia` hanya ada lokal (isinya sudah di `main`).

## 3. Hasil Verifikasi Otomatis (terbukti)

- Suite penuh di `main`: **173 lulus, 1546 assertion** (166 setelah P13.2, 172 setelah P13.3, 173 setelah perbaikan label).
- `ExportTest`: 7 test lulus (125 assertion). `ProfileTest`: 6 test lulus.
- Pint: lulus (156 berkas).
- `npm.cmd run build`: hijau, 182 modul. JS 459,07 kB (157,33 kB gzip); CSS 34,69 kB (7,45 kB gzip) ditambah 1,35 kB.
- Test frontend otomatis: tidak ada di proyek (build adalah satu-satunya pemeriksaan otomatis).

| TC | Test | Cakupan |
|---|---|---|
| 053 | `tc053_*` (2 test) | CSV percobaan dan penguasaan sama dengan hitungan manual; waktu WIB; percobaan terkirim terakhir (KT-15) |
| 054 | `tc054_*` (2 test) | Sel berawalan `=`, `+`, `-`, `@`, tab, CR diberi `'` (termasuk header konsep dan nama siswa) |
| 055 | `tc055_access_rules` | Tamu 401, siswa 403, guru kelas lain 404, guru pemilik 200 |
| 056 | `tc056_*` (4 test) | Validasi password, sukses lalu login baru, sesi lain dicabut hanya saat sukses, percobaan ke-6 mendapat 429 |
| 057 | `tc057_*` (2 test) | Nama berubah; `email` dan `role` yang dikirim diabaikan; validasi nama; tamu 401 |
| (tambahan) | `level_labels_are_indonesian` | Pemetaan `mastered`, `developing`, `not_mastered`, `insufficient` ke label Indonesia |

TC-058 (cetak) hanya dapat diuji manual.

## 4. Yang Dibangun

| API | Endpoint | Peran | Catatan |
|---|---|---|---|
| 51 | `GET /api/quizzes/{quiz}/export/attempts` | guru pemilik | CSV, satu baris per percobaan terkirim |
| 52 | `GET /api/quizzes/{quiz}/export/mastery` | guru pemilik | CSV, penguasaan per siswa per konsep dari percobaan terkirim terakhir |
| 53 | `PUT /api/profile` | G, S | hanya `name` (max 100) |
| 54 | `PUT /api/profile/password` | G, S | 204; throttle 5/menit; sesi lain dihapus |

Frontend: halaman `/account` (nama dapat diubah, email dan peran hanya baca, ganti password dengan error per-field), menu Akun di navigasi utama dan di dropdown, `ExportButtons` (dua tombol unduh), `PrintButton`, helper `lib/download.js`, serta gaya `@media print`.

## 5. Uji Manual di Browser

Hasil dilaporkan oleh pengembang (13 November 2026), semuanya **Lulus**, tanpa tangkapan layar.

| # | Skenario | Diharapkan | Status |
|---|---|---|---|
| 1 | Unduh CSV percobaan dan penguasaan | Sesuai hitungan manual; Excel/LibreOffice tanpa karakter rusak | Lulus (dijalankan sebelum label level diubah) |
| 2 | Nama `=1+1` dan `@SUM(A1)` | Tampil sebagai teks berawalan `'` | Lulus |
| 3 | Siswa dan guru kelas lain membuka URL ekspor | 403 / 404 | Lulus |
| 4 | Ganti password dengan dua browser (OI-21) | Browser kedua tidak berlaku lagi, browser pertama tetap login | Lulus |
| 5 | Password lama salah, sama dengan lama, terlalu pendek, konfirmasi beda | Pesan per-field | Lulus |
| 6 | 6 percobaan ganti password dalam semenit | Toast ramah | Lulus |
| 7 | Ubah nama | Nama di header ikut berubah; email dan peran tetap | Lulus |
| 8 | **TC-058** cetak Hasil, Perbandingan, Dashboard | Tanpa navigasi, tabel utuh, level berteks | Lulus |

## 6. Keputusan di Luar Spesifikasi

1. **OI-21:** sesi lain dihapus dengan menghapus baris `sessions` milik pengguna selain sesi aktif (bukan `logoutOtherDevices`). Hanya berlaku untuk `SESSION_DRIVER=database`. Perilaku di browser nyata dilaporkan lulus (skenario 4).
2. **OI-18:** CSV tanpa email siswa (default dokumen).
3. **Label level CSV** berbahasa Indonesia: Menguasai, Berkembang, Belum menguasai, Data belum cukup. Nilai di database tetap bahasa Inggris.
4. **Unduhan lewat axios (`responseType: 'blob'`)**, bukan tautan biasa, supaya 403/404/429 tampil sebagai toast. Isi galat blob dibaca ulang sebagai JSON.
5. **Tombol unduh** hanya muncul pada kuis terbit di pembuat kuis (tidak pada draf), dan pada Dashboard bila ada kuis dasar.
6. **Pesan validasi password:** `Password saat ini salah.` dan `Password baru harus berbeda dari password saat ini.`; 429 diganti pesan ramah di frontend.
7. Tombol Cetak, bar atas, tab kelas, tombol arsip, dan tautan kembali disembunyikan lewat `print:hidden`.

## 7. Risiko dan Sisa

- Tidak ada test frontend otomatis; regresi tampilan hanya tertangkap manual.
- Hasil uji manual bersumber dari laporan pengembang, bukan tangkapan layar. Skenario 1 dijalankan sebelum label level berubah; tampilan kolom level berbahasa Indonesia di Excel/LibreOffice belum diperiksa ulang.
- Bundel JS 157,33 kB gzip; angka sebelum Phase 13 tidak tercatat, jadi kenaikan dan kepatuhan terhadap anggaran TC-045 belum dinilai (Phase 14).
- Di Dashboard, pemilih kuis dasar dan teks "Memuat..." masih ikut tercetak. `DataTable.vue` belum diperiksa untuk perilaku `overflow` saat cetak (pratinjau dilaporkan lulus).
- Penyebab 403 sementara pada akun guru `hamasiah` (Phase 12) masih belum terbukti.
- Sisa Phase 11 yang masih terbuka: pengecualian tombol ikon 32 px (tombol baru Phase 13 memakai ukuran `sm`, bukan ikon), angka remedial beranda, alur TC-038 lokal, angka Lighthouse TC-043.

## 8. Langkah Berikutnya

1. Commit laporan ini ke `main`.
2. **Phase 14** (TC-060 sampai 063): UT-01 sampai UT-11, termasuk throttle register dan join, header keamanan, DOMPurify/MarkdownIt (OI-14), uji balapan, ukur jumlah query, regresi TC-001..063, `composer audit`, `npm audit`, uji rilis lokal `APP_ENV=production`, dan pemeriksaan anggaran bundel TC-045. Rincian di `EDUFLOW_MASTER_DOCUMENT_PHASE_11-14.md` bagian 10.
3. **Phase 10b** (hosting, TC-037, TC-038 hosted, tag `v1.0.0`) setelah Phase 14.
4. `EDUFLOW_PHASE13_LANJUTAN.md` sudah usang; tidak perlu dibawa ke sesi berikutnya.
