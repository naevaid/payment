# Deployment Notes

## Project

`payment.naeva.id` adalah centralized payment gateway service untuk proyek-proyek internal Naeva.

Tujuan utamanya:

- menjadi hub integrasi Midtrans yang terpusat
- menerima request charge dari client app internal
- menerima webhook Midtrans
- meneruskan status pembayaran ke callback URL milik project asal
- menyediakan halaman publik dasar dan fondasi login internal

Dokumen produk utama saat ini ada di `docs/PRD.md`.

## Status Saat Ini

Repository `D:\payment` sekarang sudah berisi codebase Laravel yang aktif, bukan lagi sekadar dokumen awal.

Komponen yang sudah tersedia:

- route publik `/` berupa landing page sederhana dengan tombol `Login` dan `Register`
- endpoint `/healthz` untuk health check
- route API v1 untuk charge, webhook Midtrans, project info, dan lookup transaksi
- migration awal untuk `projects`, `transactions`, `midtrans_webhook_logs`, dan `callback_forwarding_logs`
- service Midtrans, verifikasi signature webhook, queue callback forwarding, dan seeder user utama

## Akses Server

Gunakan alias SSH berikut:

```bash
ssh vps
```

Catatan:

- alias `vps` diasumsikan sudah dikonfigurasi di mesin lokal Anda
- semua perintah server di bawah dijalankan setelah masuk dengan `ssh vps`
- fokus deploy hanya untuk project `payment`, jangan menyentuh konfigurasi site lain

## Remote Repository

Repository GitHub yang dipakai:

```text
https://github.com/naevaid/payment.git
```

Alur aman:

1. kerjakan perubahan di lokal
2. commit ke git lokal
3. push ke `origin/main`
4. login ke server dengan `ssh vps`
5. pull perubahan terbaru di `/var/www/payment/repo`

## Stack VPS Aktual

Stack yang saat ini dipakai untuk deploy service ini:

- Ubuntu VPS
- Nginx
- PHP-FPM `php8.5-fpm`
- Laravel
- MySQL atau PostgreSQL untuk database aplikasi
- Redis untuk queue, cache, dan callback forwarding
- Supervisor untuk queue worker
- Let's Encrypt SSL

Kondisi server yang sudah diketahui:

- domain `payment.naeva.id` sudah live
- vhost Nginx untuk `payment.naeva.id` sudah dibuat
- SSL Let's Encrypt sudah aktif
- HTTPS sudah aktif
- `/healthz` sudah merespons `200`
- path aplikasi di server: `/var/www/payment/repo`

## Struktur Folder Server

Path kerja utama:

```text
/var/www/payment/repo
```

Deploy saat ini diasumsikan sederhana dan langsung berbasis repo kerja di path tersebut.

## Environment Production Minimum

Pastikan `.env` production minimal berisi:

```env
APP_NAME=payment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://payment.naeva.id
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payment
DB_USERNAME=payment
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_DRIVER=database
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE=payment
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE_RETRY_AFTER=120

MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_MERCHANT_ID=
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_BASE_URL=https://app.midtrans.com
MIDTRANS_SNAP_PATH=/snap/v1/transactions
MIDTRANS_TIMEOUT=10
MIDTRANS_VERIFY_SSL=true

PAYMENT_DEFAULT_CURRENCY=IDR
PAYMENT_AUTH_APP_ID_HEADER=X-App-ID
PAYMENT_AUTH_SECRET_HEADER=X-Secret-Key
PAYMENT_AUTH_SIGNATURE_HEADER=X-Payment-Signature
PAYMENT_AUTH_TIMESTAMP_HEADER=X-Timestamp
PAYMENT_AUTH_SIGNATURE_ALGORITHM=sha256
PAYMENT_AUTH_TIMESTAMP_TOLERANCE=300
PAYMENT_AUTH_ALLOW_LEGACY_SECRET_HEADER=true
PAYMENT_CALLBACK_QUEUE=payment-callbacks
PAYMENT_CALLBACK_TIMEOUT=10
PAYMENT_CALLBACK_MAX_ATTEMPTS=3
PAYMENT_CALLBACK_BACKOFF=60,300,900
PAYMENT_CALLBACK_USER_AGENT=Naeva-Payment-Callback/1.0
```

Catatan:

- di server production, gunakan kredensial Midtrans production
- jangan menyalin `.env` lokal development ke server secara mentah
- pastikan `APP_KEY` production sudah ada
- aktifkan HMAC request signing untuk client app baru; header `X-Secret-Key` hanya fallback sementara selama masa migrasi

## Auth API Tenant

Sesuai PRD, integrasi antar-server sekarang disarankan memakai signature HMAC per request.

Header minimal:

```text
X-App-ID: project_a_prod
X-Timestamp: 1760000000
X-Payment-Signature: <hmac-signature>
```

String yang ditandatangani:

```text
{HTTP_METHOD}
{REQUEST_PATH}
{APP_ID}
{UNIX_TIMESTAMP}
{SHA256_RAW_BODY}
```

Aturan:

- algoritma default: `HMAC-SHA256`
- toleransi timestamp default: `300` detik
- `X-Secret-Key` tetap didukung sementara jika `PAYMENT_AUTH_ALLOW_LEGACY_SECRET_HEADER=true`

## Route Penting

Route publik:

- `GET /`
- `GET /login`
- `GET /register`
- `GET /healthz`

Route API:

- `POST /api/v1/charge`
- `POST /api/v1/callback/midtrans`
- `GET /api/v1/projects/me`
- `GET /api/v1/transactions/{gatewayOrderId}`

## Seeder Penting

Seeder akun utama internal:

- email: `business@naeva.id`
- password: di-hash otomatis lewat model `User`

Seeder yang tersedia:

- `Database\\Seeders\\PrimaryUserSeeder`

## Alur Lokal Sebelum Push

Jalankan di lokal:

```bash
cd D:\payment
php artisan test
git status
git add .
git commit -m "Build payment API foundation and public homepage"
git push origin main
```

## Alur Deploy Ke Server

Urutan aman deploy untuk project ini:

```bash
ssh vps
cd /var/www/payment/repo
git fetch origin
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.5-fpm
sudo systemctl reload nginx
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all
```

Catatan:

- `php artisan db:seed --force` aman untuk user utama karena memakai `updateOrCreate`
- jika supervisor di server belum punya program worker khusus payment, bagian `supervisorctl` bisa gagal dan harus disesuaikan dulu
- reload Nginx dan PHP-FPM aman karena hanya reload service, bukan restart brutal

## Queue Worker

Aplikasi ini mengandalkan queue untuk callback forwarding. Minimal perlu ada worker yang memproses queue:

- connection: `redis`
- queue utama callback: `payment-callbacks`

Contoh perintah worker manual:

```bash
php artisan queue:work redis --queue=payment-callbacks --sleep=1 --tries=3 --timeout=120
```

Supervisor production sebaiknya diarahkan ke pola setara perintah di atas.

## Validasi Setelah Deploy

Minimal lakukan pengecekan:

```bash
curl -I https://payment.naeva.id
curl https://payment.naeva.id/healthz
```

Lalu validasi:

- homepage publik muncul, bukan JSON bootstrap lama
- tombol `Login` dan `Register` tampil
- endpoint `/healthz` tetap `200`
- migration berhasil masuk
- queue worker aktif
- log Laravel tidak menunjukkan error boot

## Catatan Penting

- service ini bersifat multi-tenant berbasis `app_id`
- kredensial Midtrans harus tetap terpusat di server `payment`
- webhook Midtrans wajib diverifikasi signature-nya
- callback ke project asal diproses async dan punya retry
- deploy harus berhati-hati agar tidak mengganggu project lain di VPS

## Next Step

Setelah deploy ini selesai, tahap berikut yang disarankan:

- pasang auth web internal sungguhan untuk akun utama
- buat CRUD admin untuk `projects`
- aktifkan worker supervisor khusus queue `payment-callbacks`
- uji charge sandbox end-to-end hingga callback forwarding
