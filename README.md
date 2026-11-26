# BroSeven! Laundry

Sistem manajemen bisnis laundry modern: website pengenalan bisnis, pencatatan transaksi, pemantauan status
cucian secara publik (tracking dan QR Code), pengelolaan karyawan, dan laporan pemasukan.

Dibuat oleh **BroSeven! Developer**.

## Fitur

**Website publik**
- Beranda dengan hero, tentang kami (visi dan misi), paket laundry, prosedur, FAQ, dan footer kontak.
- Cek status cucian lewat link tracking dari WhatsApp. Seluruh isi dikelola admin dari database.
- Warna website dapat dikustomisasi (pratinjau, reset ke default, penjaga keterbacaan kontras).

**Operasional laundry (admin dan karyawan)**
- Transaksi dengan kode otomatis (`BS-yymmdd-0001`), harga disimpan sebagai snapshot, total dihitung server.
- Alur status terkontrol: Diterima, Dicuci, Dikeringkan, Disetrika, Siap Diambil, Selesai, atau Dibatalkan, lengkap dengan riwayat.
- QR Code bukti transaksi dan verifikasi pengambilan sekali pakai (scan kamera atau input kode manual).
- Halaman tracking publik dengan token acak dan nama customer disamarkan.
- Pesan WhatsApp per status lewat Click-to-Chat (tidak mengklaim terkirim).
- Dashboard ringkasan, grafik, notifikasi (cucian siap diambil dan melewati estimasi), cetak bukti transaksi.

**Khusus admin**
- Laporan pemasukan (hari ini, bulan ini, tahun ini, rentang tanggal, per paket) dengan grafik, cetak, dan export Excel `.xlsx`.
- Pengaturan website: identitas, logo, favicon, kontak, media sosial, visi misi, paket, prosedur, FAQ, warna.
- **Banner promosi PNG** berisi nama bisnis, logo, dan daftar paket dalam ukuran spanduk dan standing banner,
  dengan warna mengikuti website.
- Kelola karyawan (tambah, ubah, nonaktifkan, atur ulang password, riwayat aktivitas).

**Keamanan**
- Otorisasi di backend (role, policy, gate), rate limiting, validasi, hash password, CSRF.
- Header keamanan dan Content-Security-Policy, upload gambar yang divalidasi dan disimpan ulang.
- Akun nonaktif langsung keluar dari semua perangkat.

## Teknologi

Laravel 13, PHP 8.3+, MySQL 8 / MariaDB 10.6+, Sanctum (cookie SPA), Vue 3 (Composition API), Vite, Vue Router, Pinia,
Axios, CSS manual (tanpa framework CSS), `bacon/bacon-qr-code`, `phpoffice/phpspreadsheet`, `qr-scanner`, `lucide-vue-next`.

## Hak akses

| Fitur | Admin | Karyawan |
|---|:--:|:--:|
| Dashboard (tanpa pemasukan untuk karyawan), transaksi, verifikasi QR, WhatsApp | ya | ya |
| Pengaturan akun sendiri | ya | ya |
| Laporan pemasukan, pengaturan website, banner, kelola karyawan | ya | tidak |

## Prasyarat

- PHP **8.3 atau lebih baru** dengan ekstensi: `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `fileinfo`,
  `curl`, `zip`, `gd`, `bcmath`, `dom`, `xmlwriter`, `xmlreader`, `simplexml`, `iconv`
- Composer 2, Node.js 22 LTS atau lebih baru (beserta npm), MySQL 8 atau MariaDB 10.6+, Git

Rekomendasi di Windows: **Laragon (Full)**, yang sudah membawa PHP, MySQL, Node.js, Composer, dan Git.

## Menjalankan di lokal (Windows, Laragon, VS Code)

```powershell
# 1. Masuk ke folder proyek
Set-Location D:\laragon\www\broseven-laundry

# 2. Pasang dependency
composer install
npm install

# 3. Siapkan konfigurasi
Copy-Item .env.example .env
php artisan key:generate
```

4. Buka `.env`, lalu isi `DB_PASSWORD` (jika ada) dan **`ADMIN_PASSWORD`** (wajib; minimal 8 karakter dengan huruf dan angka).

```powershell
# 5. Buat database (MySQL Laragon harus menyala)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS broseven_laundry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Buat tabel dan data awal
php artisan migrate --seed

# 7. Jalankan (dua terminal terpisah)
npm run dev
php artisan serve
```

Buka **http://localhost:8000** (gunakan `localhost`, jangan campur dengan `127.0.0.1`). Halaman login: `/login`.

**Akun awal**
- Admin: email dan password dari `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env`.
- Saat `APP_ENV=local`, seeder juga membuat data demo (paket, 30 transaksi, dan satu akun karyawan demo).
  Akun dan data demo tidak dibuat di production. Ganti atau hapus sebelum dipakai nyata.

**Pengujian**

```powershell
php artisan test
```

Perintah bantu pengembangan: `php artisan broseven:links --make-ready` membuat transaksi uji berstatus Siap Diambil
dan mencetak link tracking serta link verifikasinya.

## Variabel lingkungan penting

| Variabel | Fungsi |
|---|---|
| `APP_URL` | Alamat aplikasi. Dipakai untuk link tracking dan isi QR Code |
| `SANCTUM_STATEFUL_DOMAINS` | Domain tempat login diizinkan (harus memuat host `APP_URL`) |
| `SESSION_SECURE_COOKIE` | `true` di production (HTTPS) |
| `ADMIN_*` | Data admin awal untuk seeder |
| `CSP_MODE` | `off`, `report-only` (awal), atau `enforce` |
| `TRUSTED_PROXIES` | `*` atau IP proxy jika di belakang Cloudflare atau load balancer |
| `REPORT_EXPORT_LIMIT` | Batas baris rincian di export Excel (default 10000) |
| `BACKUP_*`, `MYSQLDUMP_PATH` | Pengaturan backup database |
| `ACTIVITY_LOG_RETENTION_DAYS` | Lama log aktivitas disimpan (minimal 30 hari) |

## Tugas terjadwal dan backup

| Perintah | Fungsi | Jadwal (WITA) |
|---|---|---|
| `php artisan broseven:backup` | Dump database terkompresi di `storage/app/private/backups` | 01:30 |
| `php artisan broseven:prune` | Hapus log aktivitas lama dan file yatim (`--dry-run` untuk uji) | 02:00 |
| `php artisan sanctum:prune-expired --hours=24` | Bersihkan token kedaluwarsa | 02:30 |

Aktifkan penjadwal dengan satu cron (Linux): `* * * * * cd /path/proyek && php artisan schedule:run >> /dev/null 2>&1`.
Backup berisi data pelanggan: salin ke penyimpanan lain secara berkala dan jangan menaruhnya di folder `public`.

## Deployment production

1. Server dengan PHP 8.3+, MySQL, Nginx atau Apache, dan **HTTPS** (wajib: pemindai QR memakai kamera dan cookie harus aman).
2. Salin proyek, lalu:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.production.example .env      # lalu isi nilainya
php artisan key:generate
php artisan migrate --force --seed
php artisan optimize
```

3. Arahkan web root ke folder `public/`. Contoh Nginx:

```nginx
server {
    listen 443 ssl http2;
    server_name laundry.contoh.com;
    root /var/www/broseven-laundry/public;
    index index.php;
    client_max_body_size 8M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

4. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh pengguna web server.
5. Login pertama, ganti password admin lewat **Pengaturan Akun**, lalu **hapus `ADMIN_PASSWORD` dari `.env`**.
6. Jalankan pemeriksaan kesiapan sampai tidak ada yang gagal:

```bash
php artisan broseven:check
```

7. Pasang cron penjadwal (lihat di atas). Setelah semua fitur diuji tanpa peringatan CSP di Console browser,
   ubah `CSP_MODE=enforce`, lalu `php artisan optimize`.

Setiap mengubah `.env`, jalankan ulang `php artisan optimize`.

## Struktur proyek
## Lisensi

Hak cipta (c) 2026 **BroSeven! Developer**. Seluruh hak dilindungi. Perangkat lunak ini berlisensi proprietari;
lihat berkas [LICENSE](LICENSE). Komponen pihak ketiga tetap tunduk pada lisensinya masing-masing.