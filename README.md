# EduFlow

Aplikasi web LMS berbasis penguasaan konsep. Guru membuat kelas, konsep, materi, dan kuis yang tiap soalnya terhubung ke satu konsep. Sistem menilai kuis, menghitung penguasaan per konsep, merekomendasikan materi remedial beserta alasannya, membuka asesmen ulang setelah remedial selesai, lalu membandingkan hasil sebelum dan sesudah.

Tampilan perbandingan memuat catatan "perubahan skor, bukan bukti sebab-akibat". EduFlow tidak mengklaim terbukti meningkatkan hasil belajar.

**Stack:** Laravel 13, Vue 3 (JavaScript), Vue Router, Pinia, Axios, Tailwind CSS 4, Vite, PostgreSQL, Laravel Sanctum (mode SPA, cookie sesi).

## Prasyarat

- PHP 8.3 atau lebih baru dengan ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip` (cek: `php -m`).
- Composer 2, Node.js dan npm, Git.
- PostgreSQL. Versi yang diuji: 18.
- Windows PowerShell: bila `npm` diblokir (`npm.ps1`), pakai `npm.cmd` atau jalankan `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.

## 1. Ambil kode dan pasang dependensi

```bash
git clone https://github.com/amdx-studio/EduFlow-App.git
cd EduFlow-App
composer install
npm install
```

## 2. Siapkan database PostgreSQL

Dev dan test memakai satu server PostgreSQL lokal dengan dua schema: `eduflow` (aplikasi) dan `eduflow_test` (test). Dua role dipakai:

- `eduflow_admin`: hanya untuk migrasi.
- `eduflow_app`: untuk aplikasi dan test. Sengaja tanpa hak DDL di schema `eduflow`.

Masuk sebagai superuser (password dimasukkan di prompt):

```bash
psql -U postgres -h localhost
```

Di prompt `postgres=#`, jalankan:

```sql
CREATE ROLE eduflow_admin LOGIN;
CREATE ROLE eduflow_app LOGIN;
CREATE DATABASE eduflow OWNER eduflow_admin;
REVOKE ALL ON DATABASE eduflow FROM PUBLIC;
GRANT CONNECT ON DATABASE eduflow TO eduflow_app;
\c eduflow
CREATE SCHEMA eduflow AUTHORIZATION eduflow_admin;
CREATE SCHEMA eduflow_test AUTHORIZATION eduflow_app;
GRANT USAGE ON SCHEMA eduflow TO eduflow_app;
ALTER DEFAULT PRIVILEGES FOR ROLE eduflow_admin IN SCHEMA eduflow GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO eduflow_app;
ALTER DEFAULT PRIVILEGES FOR ROLE eduflow_admin IN SCHEMA eduflow GRANT USAGE, SELECT ON SEQUENCES TO eduflow_app;
```

Lalu atur password kedua role. Gunakan dua password berbeda, minimal 20 karakter, tanpa spasi dan tanpa tanda kutip tunggal. Pastikan prompt berbentuk `eduflow=#` (bukan `eduflow-#`) sebelum mengetik perintah ini; password tidak tampil saat diketik:

```text
\password eduflow_admin
\password eduflow_app
\q
```

Jangan menulis password di berkas yang di-commit, di chat, atau di dokumen.

## 3. Siapkan tiga file env

Tiga file env bersifat per mesin dan tidak pernah di-commit (sudah masuk `.gitignore`).

```bash
cp .env.example .env
php artisan key:generate
```

(PowerShell: `Copy-Item .env.example .env`.)

Di `.env`, isi `DB_PASSWORD` dengan password `eduflow_app` yang sebenarnya (hapus seluruh teks placeholder berikut tanda `<` dan `>`) dan, bila memakai nama database lain, `DB_DATABASE`. **Simpan `.env` lebih dulu, baru buat `.env.testing` dan `.env.migrate`**: keduanya disalin dari `.env` dan tidak ikut berubah bila `.env` diedit kemudian. Nilai lain di `.env.example` sudah sesuai (`DB_HOST=127.0.0.1`, `DB_DATABASE=eduflow`, `DB_USERNAME=eduflow_app`, `DB_SCHEMA=eduflow`, `SESSION_DRIVER=database`).

Buat `.env.testing` dari `.env`, lalu ubah:

```text
APP_ENV=testing
DB_SCHEMA=eduflow_test
SESSION_DRIVER=array
CACHE_STORE=array
```

Buat `.env.migrate` dari `.env`, lalu ubah:

```text
DB_USERNAME=eduflow_admin
DB_PASSWORD=<password eduflow_admin>
DB_SCHEMA=eduflow
```

Nilai password yang memuat karakter khusus ditulis di dalam tanda kutip tunggal. Tanda `<` dan `>` pada contoh hanya penanda: tulis password aslinya. Bila `.env.testing` atau `.env.migrate` tidak dibuat, test dan migrasi memakai `.env` dan gagal. Akses aplikasi selalu lewat `localhost:8000` (bukan `127.0.0.1`), sesuai `SANCTUM_STATEFUL_DOMAINS`.

## 4. Migrasi

Migrasi dijalankan sebagai `eduflow_admin` lewat `--env=migrate`:

```bash
php artisan migrate --env=migrate
php artisan migrate:status
```

Harapan: semua migrasi `DONE`, lalu `migrate:status` (berjalan sebagai `eduflow_app`) menampilkan semuanya `Ran`. Setelah `git pull` yang membawa migrasi baru, jalankan perintah migrasi yang sama lagi.

## 5. Test

```bash
php artisan test
```

Test hanya berjalan di schema `eduflow_test`. Bila koneksi default bukan `pgsql` atau `search_path` bukan `eduflow_test`, test dihentikan oleh pengaman di `tests/TestCase.php`; periksa `.env.testing`. Gunakan `php artisan test --filter=NamaTest` untuk menjalankan sebagian.

Dua test (`SubmitRollbackTest`, `RaceConditionTest`) memakai `DatabaseTruncation`, bukan `RefreshDatabase`, karena membuktikan rollback dan balapan yang tidak dapat diuji di dalam pembungkus transaksi test. Setelah tiap test, trait `TruncatesAfterTest` mengosongkan **semua tabel** di schema `eduflow_test` (hanya PostgreSQL, memakai `pg_tables`). Itu aman karena pengaman di `tests/TestCase.php` membatasi test ke schema tersebut. Test balapan membuka koneksi kedua ke database yang sama.

Format kode: `vendor/bin/pint --test` (tanpa `--test` untuk memperbaiki otomatis).

## 6. Jalankan aplikasi

Di dua terminal:

```bash
php artisan serve
npm run dev
```

Buka `http://localhost:8000`. Untuk build produksi aset: `npm run build`.

Tidak ada seeder. Buat akun lewat halaman Daftar: satu akun guru (membuat kelas dan kuis) dan satu akun siswa (bergabung dengan kode kelas). Bila `TEACHER_INVITE_CODE` di `.env` terisi, pendaftaran guru meminta kode itu; kosong berarti terbuka (hanya untuk dev).

## Arsitektur singkat

`routes/api.php` → Controller (tipis) → FormRequest (validasi) → Policy dan scope kepemilikan → Service (logika) → Model; respons lewat API Resource. Aturan penguasaan, remedial, gate asesmen ulang, perbandingan, dan dashboard hanya ada di `app/Services`. Frontend Vue ada di `resources/js` dan dilayani Laravel dari satu origin. Otorisasi selalu di server; tombol yang disembunyikan di UI bukan kontrol akses. Pembatasan laju (429) didefinisikan di `AppServiceProvider`: `login`, `password-change`, `forum-write`, `register` (per IP) dan `join-course` (per pengguna); `register` dan `join-course` masing-masing 10 per menit.

## Menu kerja tim

- Branch per fase (`phase/N-nama`), commit `tipe(lingkup): ringkasan [ID]`, merge ke `main` dengan merge commit.
- Jangan menjalankan `migrate:fresh` dengan `--env=migrate` kecuali memang ingin mengosongkan database lokal sendiri.
- Bila rahasia terlanjur ter-commit, ganti kredensialnya.

## Produksi

Belum diputuskan (host dan database produksi masih terbuka). Perbedaan dari dev: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<host>`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS=<host>` (tanpa skema), `TEACHER_INVITE_CODE` terisi, password database yang kuat dan unik. `.env.migrate` tidak pernah dikirim ke server.

Header keamanan dipasang middleware `SecurityHeaders` pada semua respons. `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, dan `Permissions-Policy` aktif di semua lingkungan. `Content-Security-Policy` hanya pada `APP_ENV=production` (saat `npm run dev`, Vite memuat skrip dari server lain dan menyisipkan gaya inline). `Strict-Transport-Security` hanya pada production dan permintaan HTTPS, `max-age=15552000` tanpa `includeSubDomains` dan `preload`. Di balik proxy hosting, HTTPS baru terdeteksi bila trusted proxies dikonfigurasi (belum ada di `bootstrap/app.php`; dicatat untuk Phase 10b).

## Masalah umum

| Gejala | Penyebab dan langkah |
|---|---|
| `could not find driver` | Ekstensi `pdo_pgsql` belum aktif di `php.ini` |
| `password authentication failed` | Password role belum diatur atau salah (bagian 2 dan 3) |
| `connection refused` | Layanan PostgreSQL berhenti atau port bukan 5432 |
| `permission denied for schema eduflow` saat migrasi | Migrasi dijalankan tanpa `--env=migrate` |
| Test berhenti dengan pesan koneksi atau `search_path` | `.env.testing` belum benar (bagian 3) |
| `no password supplied` saat test atau migrasi | `DB_PASSWORD` kosong di salah satu file env, atau `.env.testing` / `.env.migrate` belum dibuat dari `.env` yang sudah terisi (bagian 3) |
| Login gagal atau error 419 | Buka lewat `http://localhost:8000` dan periksa `APP_URL` serta `SANCTUM_STATEFUL_DOMAINS` |
