# EduFlow Phase 12: Forum Diskusi Kelas (Laporan Final)

Tanggal: 13 November 2026. Cakupan: API-43 sampai 50, US-023 sampai US-025, BR-019 sampai BR-022, KT-26, OI-20, TC-046 sampai TC-052.

## 1. Ringkasan

Backend dan frontend forum selesai dan ter-merge ke `main` (`591c9bf`). Seluruh pengujian otomatis lulus. Uji manual di browser (bagian 5) sudah dijalankan: **semua 11 skenario dilaporkan Lulus oleh pengembang**, termasuk XSS (no. 10), RLS (no. 11), dan non-anggota (no. 7). **Phase 12 dinyatakan tuntas.**

Satu kejadian tercatat saat uji: 403 sementara pada tab Siswa dan Dashboard akun guru (bagian 6). Penyebabnya belum terbukti.

## 2. Riwayat Commit

| Langkah | Commit | Isi |
|---|---|---|
| P12.1 | `e5d02e5` | Migrasi, model, `ForumThreadPolicy`, `ForumPostPolicy`, binding |
| P12.2 | `8a14b1e` | `ThreadController`, `PostController`, FormRequest, Resource, throttle, kursor |
| P12.3 | `39a8fae` | `ForumTest` (TC-046..052) dan perluasan `AccessHardeningTest` |
| Merge backend | `5d0ccb5` | `phase/12-forum-backend` ke `main` (`--no-ff`) |
| P12.4 | `f268655` | Halaman Forum dan Utas, `ThreadForm`, `PostForm`, tab Forum, rute |
| Merge UI | `591c9bf` | `phase/12-forum-ui` ke `main` (`--no-ff`) |

Status git terakhir diperiksa: `main` = `origin/main` = `591c9bf`, bersih.

## 3. Hasil Verifikasi Otomatis (terbukti dari keluaran perintah)

- Suite penuh di `main` setelah kedua merge: **160 lulus, 1361 assertion**.
- Pint: lulus (149 berkas).
- `npm.cmd run build`: hijau (178 modul).
- `ForumTest`: 17 test lulus (TC-046 sampai 052). `AccessHardeningTest`: 4 test lulus dan mencakup 8 route forum (401 tamu, 403/404 aktor ditolak, id ngawur, input ngawur).
- `php artisan test --filter=Dashboard` (13 Nov 2026, saat penelusuran 403): 6 test lulus, 66 assertion, termasuk `tc035 access rules` dan `tc059`.
- Test frontend otomatis: tidak ada di proyek (build adalah satu-satunya pemeriksaan).

Pemetaan test:

| TC | Test | Cakupan |
|---|---|---|
| 046 | `tc046_*` (3 test) | Buat utas, muncul di daftar, non-anggota 404, tamu 401, validasi judul dan isi, batas 150 dan 10.000 |
| 047 | `tc047_*` (3 test) + `reply_limit_*` | Balasan berurutan, utas terkunci 409, validasi dan sunting balasan, batas 200 balasan |
| 048 | `tc048_*` (4 test) | Moderasi hanya guru pemilik, sunting hanya penulis, aturan hapus (OI-20), 404 untuk guru lain dan non-anggota |
| 049 | `tc049_server_*` | Sisi server: teks berbahaya disimpan dan dikembalikan apa adanya sebagai JSON. Sisi tampilan dibuktikan manual (no. 10) |
| 050 | `tc050_*` | Kelas arsip: baca boleh, semua tulis dan moderasi 409 |
| 051 | `tc051_*` (2 test) | Kursor 20 + 5 tanpa duplikat, tepat 20 tanpa `has_more`, `before_id` ngawur 422 |
| 052 | `tc052_*` | Posting ke-21 dalam semenit 429, batas dipakai bersama utas dan balasan |

## 4. Keamanan Data (RLS)

Tabel forum tidak otomatis ber-RLS. `php artisan eduflow:secure-schema --env=migrate` dijalankan di database dev: RLS dan policy `app_full_access` aktif pada 18 tabel; `forum_threads` dan `forum_posts` kini `rls=true` dengan policy untuk `eduflow_app`. Lingkungan lain yang memakai skrip ini harus menjalankan `migrate` lalu `secure-schema` setelah menarik `main`.

## 5. Uji Manual di Browser

Hasil dilaporkan oleh pengembang (tanggal 13 November 2026). Catatan di kolom terakhir.

| # | Skenario | Diharapkan | Status | Catatan |
|---|---|---|---|---|
| 1 | Tab Forum | Guru: Materi, Konsep, Kuis, Siswa, Dashboard, Forum. Siswa: Materi, Kuis, Remedial Saya, Forum. Tab aktif di halaman utas | Lulus | Lihat insiden 403 (bagian 6) |
| 2 | Buat utas (siswa) | Pratinjau Markdown dan penghitung karakter jalan, pindah ke utas, toast | Lulus | |
| 3 | Balasan | Urutan lama ke baru, nama dan lencana peran benar, sunting hanya milik sendiri | Lulus | |
| 4 | Moderasi (guru) | Sematkan ke bagian "Disematkan", kunci menyembunyikan form balasan, siswa tidak melihat tombolnya | Lulus | |
| 5 | Hapus utas | Penulis siswa pada utas yang sudah dibalas orang lain ditolak (toast), guru dapat menghapus | Lulus | |
| 6 | Kelas arsip | Banner baca-saja, semua tombol tulis hilang | Lulus | |
| 7 | Non-anggota | `/courses/{id}/forum` menampilkan "Kelas tidak ditemukan" | Lulus | |
| 8 | Paginasi | 20 utas, "Muat lebih banyak" menambah sisanya, tanpa duplikat | Lulus | |
| 9 | Throttle | 21 posting per menit memunculkan toast ramah | Lulus | |
| 10 | **XSS (TC-049)** | `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`, `[klik](javascript:alert(1))` di judul, isi utas, dan balasan: tidak ada skrip berjalan | Lulus | Jalur `MarkdownView` (`html: false` + DOMPurify) kini terbukti |
| 11 | RLS aktif | Daftar, buat utas, dan balas berfungsi setelah `secure-schema` | Lulus | |

## 6. Insiden Selama Uji: 403 pada Tab Siswa dan Dashboard (guru)

Pada akun guru `hamasiah` (id 5, pemilik Kelas 2 "Micro Teaching"), tab Siswa dan Dashboard sempat menampilkan "Anda tidak memiliki akses ke sumber daya ini", sedangkan tab Materi, Konsep, Kuis, dan Forum normal. Kode gabung juga sempat kosong.

Yang terbukti:

- Pesan itu berasal dari `abort(403)` di `EnsureRole`. Dua rute yang gagal memakai `role:teacher` (`courses/{course}/students` dan `courses/{course}/dashboard`).
- Di database, `users.role = "teacher"` dan `courses.teacher_id = 5` (kepemilikan benar sejak awal; `update` di tinker tidak mengubah apa pun).
- Log sementara di `EnsureRole` mencatat request ke kedua rute itu dengan `id: 5`, `role: teacher`. Artinya `EnsureRole` meloloskan akun tersebut pada saat dicek.
- Test dashboard lulus (bagian 3). Baris log sementara sudah dibuang (`git checkout`), tidak ada perubahan kode.

Yang tidak terbukti: penyebab 403 awal. Dugaan terkuat adalah sesi login di browser sempat tidak sinkron, tetapi ini dugaan. Bila terulang, catat status dan isi respons dari DevTools (tab Network) sebelum memuat ulang.

## 7. Keputusan di Luar Spesifikasi

1. 403 sebelum 422 untuk `PUT` (otorisasi per kolom di `authorize()` FormRequest). Guru pemilik tidak dapat menyunting judul atau isi milik orang lain.
2. Kelas arsip: semua aksi tulis, termasuk hapus, memberi 409 (spesifikasi hanya menyebut "tulis dan moderasi").
3. Batas 200 balasan per utas memberi 409 pada balasan ke-201 (AT-10).
4. Utas terkunci: penulis tetap boleh menyunting isinya; balasan baru ditolak dari siapa pun.
5. Guru pemilik boleh membuat utas.
6. Halaman forum membaca status arsip lewat `provide('course')` dari `CourseLayout`, tanpa permintaan tambahan.
7. 429 dari Laravel (bahasa Inggris) diganti pesan ramah di sisi frontend (`lib/forum.js`).

## 8. Risiko dan Sisa

- Tidak ada test frontend otomatis; regresi tampilan hanya tertangkap manual.
- Sisa Phase 11 yang masih terbuka: pengecualian tombol ikon 32 px (relevan untuk tombol ikon baru), angka remedial beranda, alur TC-038 lokal, angka Lighthouse TC-043.
- Penyebab 403 sementara (bagian 6) belum terbukti.
- Hasil uji manual bersumber dari laporan pengembang, bukan dari tangkapan layar atau log yang terlampir.

## 9. Langkah Berikutnya

1. Commit laporan ini ke `main`.
2. **Phase 13: Ekspor, Cetak, dan Akun** (US-026 sampai 030, API-51 sampai 54, TC-053 sampai 058; sumber: `EDUFLOW_MASTER_DOCUMENT_PHASE_11-14.md` bagian 10). Cabang `phase/13-ekspor-akun` sudah dibuat; P13.0 (baseline) sedang berjalan.
3. Phase 14, lalu Phase 10b (hosting).
4. Dokumen `EDUFLOW_PHASE12_LANJUTAN.md` sudah usang (P12.4 dan P12.5 selesai dan ada di `main`); tidak perlu dibawa ke sesi berikutnya.
