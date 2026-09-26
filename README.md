# TIK Mission — Operasi File Rahasia

Game pembelajaran **Informatika Kelas 8 SMP/MTs — Bab 3 Teknologi Informasi dan Komunikasi**.
Siswa bekerja dalam kelompok dan menyelesaikan misi **praktik langsung di komputer**
(Microsoft Word/Excel/PowerPoint, Snipping Tool, virtual lab), lalu mengunggah **bukti screenshot**
untuk divalidasi guru.

Prinsip utama: **AI boleh membantu mencari langkah, tetapi praktik tetap dilakukan siswa sendiri.**

---

## 1. Fitur Utama

| Area | Fitur |
| --- | --- |
| **Guru/Admin** | Login, buat sesi, atur timer, kelola kelompok, validasi bukti, lulus/perbaikan, XP, laporan, export CSV, reset progres, akhiri sesi, kelola misi |
| **Siswa** | Masuk dengan **kode sesi + nama kelompok**, lihat misi, baca instruksi, petunjuk, unggah bukti, timer, XP, progres |
| **Sistem Misi** | 4 misi utama + 1 final mission, status kunci otomatis (LOCKED → AVAILABLE → IN PROGRESS → WAITING VALIDATION → COMPLETED) |
| **Keamanan** | CSRF, whitelist upload, random filename, bcrypt, role authorization |

### Daftar Misi

| # | Misi | Fokus Materi |
| --- | --- | --- |
| 1 | Operasi Format | Page, paragraph, header/footer, formatting, Save As |
| 2 | Operasi Clipboard | Cut, Copy, Paste, clipboard antar-aplikasi |
| 3 | Screenshot Investigator | Screenshot, Snipping Tool, area selection |
| 4 | Operasi Laporan | Menggabungkan teks, tabel, gambar jadi laporan; PDF |
| 5 | Final Mission — Virtual Lab | Digital content, input-process-output |

---

## 2. Teknologi

- **PHP 8.3+** (diuji pada PHP 8.3.30)
- **Laravel 13**
- **MySQL/MariaDB** (diuji pada MySQL 8.4)
- **Blade** + **Tailwind CSS v4** + **Alpine-free vanilla JS** (ringan untuk komputer lab)
- **Vite 8** (hanya saat build asset)
- Tanpa Docker, tanpa API eksternal, tanpa microservice

---

## 3. Struktur Project

```text
app/
├── Console/Commands/
│   └── ResetDemo.php              # php artisan tik:reset-demo
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── Student/
│   │   │   ├── JoinController.php         # masuk kode sesi + kelompok
│   │   │   └── MissionController.php      # dashboard, misi, submit, hint
│   │   └── Teacher/
│   │       ├── DashboardController.php    # dashboard, sesi, CRUD sesi
│   │       ├── TeamController.php         # kelompok, validasi bukti
│   │       ├── ReportController.php       # laporan + export CSV
│   │       └── MissionController.php      # kelola isi misi
│   └── Middleware/
│       ├── EnsureTeacher.php              # hanya guru
│       └── EnsureStudentTeam.php          # hanya siswa yang sudah masuk
├── Models/                        # User, GameSession, Mission, Team,
│                                  # TeamMember, TeamProgress, Submission
├── Providers/AppServiceProvider.php
└── Services/
    ├── MissionService.php         # kunci/buka misi
    ├── SubmissionService.php      # upload & simpan bukti
    ├── ScoringService.php         # XP, bonus, penalti petunjuk
    ├── StudentAuthService.php     # identitas kelompok berbasis token
    └── TeamService.php            # buat/reset/hapus kelompok

config/tikmission.php              # semua pengaturan game (XP, upload, folder, dsb.)

database/
├── migrations/                    # 9 tabel aplikasi
└── seeders/
    ├── DatabaseSeeder.php         # guru, 5 misi, sesi demo
    └── PracticeFileSeeder.php     # file praktik .docx/.xlsx/.pptx/.zip

resources/views/
├── layouts/                       # teacher, student, guest
├── components/                    # stat-card, status-badge, flash
├── auth/login.blade.php
├── student/                       # landing, join, dashboard, mission
└── teacher/                       # dashboard, sessions, teams, validations,
                                   # report, missions

routes/web.php                     # 37 route
storage/app/public/
├── practice/                      # file praktik untuk siswa
└── evidence/                      # bukti unggahan siswa
```

---

## 4. Skema Database

```text
users              id, name, email, password, role, timestamps
game_sessions      id, code(unique), name, duration_minutes, start_time, end_time,
                   status, leaderboard_enabled, hints_enabled, is_demo, timestamps
missions           id, order(unique), title, slug(unique), difficulty, story, objective,
                   instructions(json), hint_1, hint_2, reflection_question,
                   xp, requires_pdf, is_active, timestamps
teams              id, game_session_id→, name, token(unique,64), xp, started_at,
                   completed_at, timestamps   UNIQUE(game_session_id, name)
team_members       id, team_id→, name, timestamps
team_progress      id, team_id→, mission_id→, status, xp, hints_used, unlocked_at,
                   completed_at, timestamps   UNIQUE(team_id, mission_id)
submissions        id, team_id→, mission_id→, answer, evidence_path, file_path,
                   status, teacher_comment, submitted_at, validated_at, timestamps
```

> Catatan: tabel session framework dinamai `laravel_sessions` agar tidak bentrok dengan `game_sessions`.

**Relasi kunci**
- `game_sessions` 1—N `teams` 1—N `team_members`
- `teams` N—M `missions` melalui `team_progress`

**Status misi** (`team_progress.status`): `locked`, `available`, `in_progress`, `waiting_validation`, `completed`

---

## 5. Akun Demo & Data Awal

| Peran | Kredensial |
| --- | --- |
| **Guru** | Diatur lewat `TEACHER_EMAIL` / `TEACHER_PASSWORD` di `.env` |
| **Sesi demo** | Kode: `TIK8-DEMO` (petunjuk aktif) |
| **Siswa** | Cukup kode sesi + nama kelompok + nama anggota (tanpa akun) |

> **Password guru tidak lagi memakai bawaan "password".**
> Seeder membaca `TEACHER_EMAIL` dan `TEACHER_PASSWORD` dari `.env`.
> Bila `TEACHER_PASSWORD` kosong, seeder membuat password acak kuat dan
> menampilkannya **sekali** di layar saat seeding — simpan password itu.
>
> Untuk mengganti password guru kapan saja:
> `php artisan tinker --execute="\App\Models\User::where('email','admin@example.com')->update(['password'=>bcrypt('PASSWORD-BARU')]);"`

---

## 6. Cara Menjalankan Lokal (Laragon / Windows)

Asumsi Laragon terpasang di `C:\laragon`. PHP dan Composer Laragon berada di luar PATH,
sehingga dipanggil dengan path lengkap.

```powershell
# 0. Masuk ke folder project
cd C:\laragon\www\bab3

# 1. Install dependency PHP
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" "C:\laragon\bin\composer\composer.phar" install

# 2. Siapkan .env (sudah disertakan untuk lokal, sesuaikan bila perlu)
#    Pastikan extension=zip aktif di php.ini (untuk membuat file praktik).
#    Buka C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini, ubah:
#       ;extension=zip   ->   extension=zip

# 3. Buat database MySQL
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS tik_mission CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Generate key (lewati bila .env sudah berisi APP_KEY)
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan key:generate

# 5. Migrasi + seeder (guru, 5 misi, sesi demo, file praktik)
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan migrate --seed

# 6. Symlink storage (wajib agar bukti & file praktik dapat diakses)
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan storage:link

# 7. Build aset frontend
npm install
npm run build

# 8. Jalankan
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan serve
```

Buka **http://127.0.0.1:8000**

> Jika PHP/Composer sudah ada di PATH, cukup pakai `php` dan `composer`.

### Alamat Penting

| URL | Keterangan |
| --- | --- |
| `/` | Halaman awal siswa |
| `/student/join` | Form masuk kelompok |
| `/student/dashboard` | Dashboard misi siswa |
| `/login` | Login guru |
| `/teacher` | Dashboard guru |
| `/storage/practice/mission-01-format.docx` | Unduh file praktik |

---

## 7. Cara Menjalankan Test

```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test
```

Hasil saat ini: **46 test, 140 assertion, semuanya lulus.**

Cakupan test: login guru, authorization role, masuk kode sesi, penguncian misi,
upload bukti, validasi file (ekstensi & ukuran), validasi guru, XP, petunjuk,
timer, dan export CSV.

---

## 8. Cara Reset Data Demo

```powershell
# Reset sesi demo (hapus kelompok + bukti, timer mulai ulang)
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan tik:reset-demo

# Hapus SEMUA sesi dan kelompok (diminta konfirmasi)
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan tik:reset-demo --all

# Sertakan pembersihan folder bukti
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan tik:reset-demo --all --files

# Bangun ulang data demo dari nol
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan migrate:fresh --seed
```

### Demo Mode untuk Guru

1. Login guru → **Sesi** → sesi `TIK8-DEMO` sudah siap pakai.
2. Buka sesi, lalu **Jalankan Ronde** dari layar proyektor.
3. Buka jendela browser lain (mode incognito) → `http://127.0.0.1:8000/student/join`.
4. Masuk dengan `TIK8-DEMO` + nama kelompok bebas.
5. Selesaikan misi, unggah bukti, lalu validasi dari panel guru.
6. Setelah selesai, jalankan `php artisan tik:reset-demo` untuk mengulang.

---

## 9. Cara Menambah / Mengubah Misi

### A. Mengubah misi yang ada (tanpa coding)

1. Login guru → menu **Misi**.
2. Klik **Edit Misi**.
3. Ubah judul, cerita, tujuan, **instruksi (satu langkah per baris)**, XP, dan petunjuk.
4. Simpan.

### B. Menambah misi baru

Tambahkan entri ke array `$missions` di `database/seeders/DatabaseSeeder.php`:

```php
[
    'order' => 6,                          // urutan misi (harus unik)
    'title' => 'Misi 06 — Nama Misi Baru',
    'slug' => 'misi-06-baru',              // harus unik
    'difficulty' => 'Sedang',              // Mudah | Sedang | Sulit
    'xp' => 100,
    'story' => 'Cerita pembuka misi...',
    'objective' => 'Tujuan pembelajaran...',
    'instructions' => [
        'Langkah praktik pertama.',
        'Langkah praktik kedua.',
    ],
    'hint_1' => 'Petunjuk pertama (arahan, bukan jawaban).',
    'hint_2' => 'Petunjuk kedua (shortcut atau cara spesifik).',
    'reflection_question' => 'Pertanyaan refleksi singkat.',
    'requires_pdf' => false,
],
```

lalu jalankan:

```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan db:seed --class=DatabaseSeeder
```

> Seeder bersifat *idempotent* (`updateOrCreate`), jadi aman dijalankan ulang.
> Kelompok yang sudah ada otomatis mendapat baris progres untuk misi baru saat berikutnya membuka dashboard.

---

## 10. Cara Mengganti File Praktik

File praktik berada di **`storage/app/public/practice/`**:

```text
mission-01-format.docx          Misi 01 — dokumen berantakan
mission-02-word.docx            Misi 02 — sumber Copy (Word)
mission-02-data.xlsx            Misi 02 — tabel sumber (Excel)
mission-02-slide.pptx           Misi 02 — tujuan Paste (PowerPoint)
mission-03-screenshot.docx      Misi 03 — dokumen kerja
mission-04-report-assets.zip    Misi 04 — bahan laporan (teks, CSV, gambar PNG)
README-FILE-PRAKTIK.txt         Petunjuk untuk guru
```

**Mengganti dengan file versi sendiri:**

1. Siapkan file Word/Excel/PowerPoint Anda.
2. Beri nama **sama persis** seperti daftar di atas.
3. Timpa file di `storage/app/public/practice/`.
4. Tidak perlu mengubah kode.

Siswa mengunduh lewat: `https://domain-anda.com/storage/practice/nama-file.docx`

> Seeder membuat file versi **minimal** agar aplikasi langsung bisa dipakai.
> Untuk aktivitas kelas yang lengkap, guru sebaiknya menyiapkan file versi sendiri.

---

## 11. Deploy ke CyberPanel (OpenLiteSpeed)

### Requirement

- PHP **8.2+** (disarankan 8.3) dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`
- MySQL / MariaDB
- Composer
- Node.js (hanya saat build asset)
- OpenLiteSpeed + CyberPanel

### Langkah Deployment

**1. Buat website & database di CyberPanel**

- Websites → Create Website (mis. `tik.sekolah.sch.id`)
- Databases → Create Database → catat nama DB, user, dan password

**2. Clone project**

```bash
cd /home/domain.com/public_html
git clone <URL-REPO> .
# atau upload file zip lalu ekstrak
```

**3. Install dependency & konfigurasi**

```bash
composer install --no-dev --optimize-autoloader

cp .env.example .env
nano .env      # isi APP_URL, DB_*, dan STUDENT_TOKEN_SALT
```

Isi `.env` produksi:

```env
APP_NAME="TIK Mission"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tik.sekolah.sch.id

APP_LOCALE=id
APP_FALLBACK_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=nama_user
DB_PASSWORD=password_database

STUDENT_TOKEN_SALT="string-acak-panjang-unik"
UPLOAD_MAX_KB=10240

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

**4. Build aset frontend**

```bash
npm install
npm run build
# Setelah build, folder node_modules boleh dihapus untuk menghemat ruang:
# rm -rf node_modules
```

**5. Set permission**

```bash
chown -R domain.com:domain.com storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**6. Optimasi produksi**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Setiap kali mengubah `.env` atau kode, jalankan `php artisan optimize:clear` lalu cache ulang.

### Document Root

Arahkan document root ke folder **`public`**:

```text
/home/domain.com/public_html/public
```

Di CyberPanel: **Websites → Manage → Document Root**, isi `/public_html/public`.

**Alternatif** (jika tidak bisa mengubah document root) — buat `.htaccess` di root project:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### Konfigurasi Rewrite Laravel di OpenLiteSpeed

Buat file `public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

Di CyberPanel: **Websites → Manage → Rewrite Rules** — tempel aturan rewrite di atas
(jika OLS tidak membaca `.htaccess`, salin isinya ke kolom Rewrite Rules).

Setelah itu pastikan route berikut dapat diakses:

```text
/                 → 200 halaman awal
/login            → 200 form login guru
/student/join     → 200 form masuk siswa
/teacher          → redirect ke /login (jika belum login)
```

### Konfigurasi PHP di CyberPanel

- **PHP Version**: pilih 8.2 atau 8.3
- **PHP Extensions**: aktifkan `zip`, `fileinfo`, `pdo_mysql`, `mbstring`, `bcmath`
- **upload_max_filesize** dan **post_max_size**: minimal `12M` (agar upload bukti 10 MB berhasil)
- **max_execution_time**: minimal `60`

### Scheduler (opsional)

Jika ingin auto-expire sesi, tambahkan cron di CyberPanel:

```bash
* * * * * cd /home/domain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

### Troubleshooting Umum

| Masalah | Solusi |
| --- | --- |
| Halaman putih / error 500 | Set `APP_DEBUG=true` sementara, cek `storage/logs/laravel.log`, pastikan permission `storage` 775 |
| 404 pada semua route selain `/` | Document root belum ke `/public`, atau rewrite belum aktif |
| Gambar bukti tidak muncul | `php artisan storage:link` belum dijalankan, atau symlink tidak diizinkan → salin manual folder `storage/app/public` ke `public/storage` |
| Upload gagal file besar | Naikkan `upload_max_filesize` & `post_max_size` di PHP OLS |
| File praktik tidak terbuat | Ekstensi `zip` belum aktif → aktifkan lalu jalankan `php artisan db:seed --class=PracticeFileSeeder` |
| CSS/JS tidak termuat | `npm run build` belum dijalankan, atau `public/build` hilang |

---

## 12. Pengaturan Game

Semua nilai dapat diubah di **`config/tikmission.php`**:

| Key | Default | Keterangan |
| --- | --- | --- |
| `upload_max_kb` | 10240 | Batas upload (KB) = 10 MB |
| `allowed_extensions` | jpg, jpeg, png, webp, pdf, docx | Whitelist ekstensi bukti |
| `hint_penalty_xp` | 10 | XP dikurangi per petunjuk |
| `no_hint_bonus_xp` | 20 | Bonus tanpa petunjuk |
| `on_time_bonus_xp` | 10 | Bonus tepat waktu |
| `duration_options` | 0, 30, 45, 60, 90 | Pilihan durasi timer sesi |
| `demo_session_code` | TIK8-DEMO | Kode sesi demo |

**Rumus XP** (dihitung saat guru menyatakan *Lulus*):

```text
XP = XP_misi
   + bonus_tanpa_petunjuk   (jika hints_used = 0)
   + bonus_tepat_waktu      (jika sesi bertimer & waktu belum habis)
   - (hints_used × hint_penalty_xp)

XP minimal 0.
```

---

## 13. Alur Pemakaian di Laboratorium

```text
GURU
  ↓  Login → Buat Sesi → dapat kode (mis. TIK8-2026)
  ↓  Bagikan kode + file praktik ke siswa
SISWA
  ↓  Buka halaman awal → Mulai Misi
  ↓  Masukkan kode sesi + nama kelompok + anggota
  ↓  MISSION 01: praktik di Word → screenshot → unggah bukti
  ↓  (opsional) pakai petunjuk → XP berkurang
  ↓  Status: WAITING VALIDATION
GURU
  ↓  Validasi → periksa bukti → Lulus / Perlu Perbaikan
  ↓  Jika Lulus: XP bertambah + MISSION 02 terbuka
SISWA
  ↓  MISSION 02 → 03 → 04 → FINAL MISSION
  ↓  SELESAI (XP total tampil di dashboard)
GURU
  ↓  Laporan → Export CSV
```

**Aturan AI** yang ditampilkan di halaman siswa:

- ✅ Boleh: mencari langkah penggunaan aplikasi, memahami istilah, mencari shortcut, meminta contoh
- ❌ Tidak boleh: menyuruh AI mengerjakan seluruh tugas, membuat bukti palsu, mengunggah hasil yang tidak dikerjakan sendiri

---

## 14. Keamanan yang Diterapkan

- **CSRF protection** pada semua form (token Blade `@csrf`)
- **Validasi upload**: whitelist ekstensi, batas ukuran 10 MB, validasi MIME
- **Random filename**: `{32-char-random}_{nama-aman}.{ext}` mencegah tabrakan & path traversal
- **Sanitasi nama file**: hanya huruf/angka/dash/underscore
- **Role authorization**: middleware `teacher` & `student`; siswa tidak bisa membuka `/teacher`
- **Identitas siswa**: token 64 karakter di DB + verifikasi `hash_equals`; token palsu langsung ditolak
- **Status misi server-side**: siswa tidak bisa mengubah status via request manual (diverifikasi ulang di controller + test otomatis)
- **Password**: bcrypt (Laravel default), tidak pernah plaintext
- **Rate limiting**: login (10/menit), join (20/menit), submit (20/menit), hint (30/menit)
- **Pesan error Bahasa Indonesia** sederhana, tanpa bocoran detail teknis saat produksi

---

## 15. Lisensi

Dibuat untuk keperluan pembelajaran Informatika SMP/MTs.
Silakan sesuaikan dengan kebutuhan sekolah Anda.
