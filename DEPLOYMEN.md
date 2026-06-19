# Deployment Notes

## Project

`payment.naeva.id` adalah centralized payment gateway service untuk proyek-proyek internal Naeva.

Tujuan utamanya:

- menjadi hub integrasi Midtrans yang terpusat
- menerima request charge dari client app internal
- menerima webhook Midtrans
- meneruskan status pembayaran ke callback URL milik project asal

Dokumen produk utama saat ini ada di `docs/PRD.md`.

## Status Saat Ini

Saat file ini dibuat, repository `D:\payment` masih berisi dokumen PRD dan belum berisi codebase aplikasi final.

Karena itu, panduan deploy ini dibagi menjadi 2 tahap:

1. bootstrap server dan folder project
2. deploy/update code setelah aplikasi sudah dibangun

## Akses Server

Gunakan alias SSH berikut:

```bash
ssh vps
```

Catatan:

- alias `vps` diasumsikan sudah dikonfigurasi di mesin lokal Anda
- semua perintah server di bawah dijalankan setelah masuk dengan `ssh vps`

## Remote Repository

Repository GitHub yang dipakai untuk project ini adalah:

```text
https://github.com/naevaid/payment.git
```

Alur yang disarankan:

1. kerjakan perubahan di lokal
2. commit ke git lokal
3. push ke `origin/main`
4. baru login ke server dengan `ssh vps`
5. pull perubahan terbaru di server

Ini lebih aman dibanding mengedit langsung di server karena histori perubahan tetap rapi dan rollback lebih mudah.

## Rekomendasi Stack VPS

Untuk kebutuhan `payment.naeva.id`, stack yang paling masuk akal di VPS adalah:

- Ubuntu VPS
- Nginx
- PHP 8.3
- Laravel
- PostgreSQL atau MySQL
- Redis
- Supervisor
- Certbot atau reverse proxy HTTPS yang setara

Alasan rekomendasi ini:

- service ini dominan API, webhook, queue, retry job, dan dashboard internal ringan
- Laravel sangat cocok untuk webhook processing, queue worker, scheduler, dan admin panel sederhana
- Redis memudahkan queue forwarding callback async
- Nginx + PHP-FPM + Supervisor adalah pola yang stabil dan ringan di VPS

Jika nanti diputuskan stack lain, file ini bisa diperbarui. Tetapi untuk fase awal, asumsi deploy paling aman adalah stack di atas.

## Struktur Folder Server

Project ini disarankan ditempatkan di:

```text
/var/www/payment
```

Struktur minimum awal:

```text
/var/www/payment
  /app
  /shared
  /releases
  /repo
```

Arti folder:

- `app`: symlink atau copy release aktif
- `shared`: file persistent seperti `.env`, storage, atau asset runtime
- `releases`: hasil rilis per timestamp jika nanti memakai model zero-downtime sederhana
- `repo`: clone git utama

Jika ingin sederhana di fase awal, cukup pakai:

```text
/var/www/payment/repo
```

## Bootstrap Server Awal

Jalankan di server:

```bash
ssh vps
sudo mkdir -p /var/www/payment/repo
sudo chown -R $USER:$USER /var/www/payment
cd /var/www/payment
```

Jika repository GitHub sudah siap:

```bash
git clone https://github.com/naevaid/payment.git /var/www/payment/repo
cd /var/www/payment/repo
```

Jika folder sudah ada dan tinggal update:

```bash
cd /var/www/payment/repo
git fetch origin
git pull origin main
```

## Checklist Sebelum Deploy Code

Sebelum aplikasi benar-benar dideploy, minimal harus sudah jelas:

- stack aplikasi final
- kebutuhan runtime: PHP-FPM, Redis, database, queue worker
- database yang dipakai
- queue/worker yang dipakai
- environment variables untuk Midtrans
- domain dan reverse proxy yang akan dipakai

Karena berdasarkan `docs/PRD.md`, service ini akan menangani webhook dan forwarding async, maka sangat disarankan sejak awal menyiapkan:

- database transaksi
- queue worker
- retry mechanism
- log monitoring

## Catatan SSL Sertifikat

Domain `payment.naeva.id` saat ini perlu diatur SSL sertifikatnya terlebih dulu sebelum dianggap siap production.

Prioritas urutannya:

1. pastikan DNS `payment.naeva.id` sudah mengarah ke VPS yang benar
2. siapkan vhost Nginx untuk domain `payment.naeva.id`
3. terbitkan SSL sertifikat
4. aktifkan redirect HTTP ke HTTPS
5. baru lakukan uji webhook dan callback production

Jika memakai Certbot di VPS, pola umumnya:

```bash
ssh vps
sudo certbot --nginx -d payment.naeva.id
```

Jika SSL masih belum aktif, jangan pakai endpoint production Midtrans karena webhook dan callback service ini wajib berjalan lewat HTTPS.

## Rekomendasi Environment

Karena fungsi proyek ini kritikal, environment minimum yang disarankan:

- Nginx sebagai reverse proxy
- PHP-FPM untuk menjalankan aplikasi
- queue worker supervisor
- Redis untuk queue
- PostgreSQL atau MySQL untuk transaksi
- HTTPS aktif dengan sertifikat valid untuk `payment.naeva.id`
- secret Midtrans hanya di server

## Draft Variable Yang Akan Dibutuhkan

Nama final bisa berubah sesuai stack, tetapi secara konsep minimal akan butuh:

```env
APP_NAME=payment
APP_ENV=production
APP_URL=https://payment.naeva.id

APP_DEBUG=false

DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis

MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=true

LOG_LEVEL=info
```

## Alur Lokal Sebelum Deploy

Setelah repository lokal sudah menjadi git repo:

```bash
cd D:\payment
git status
git add .
git commit -m "Initial payment service docs and deployment notes"
git push -u origin main
```

Jika belum ada repo lokal:

```bash
cd D:\payment
git init -b main
git remote add origin https://github.com/naevaid/payment.git
git add .
git commit -m "Initial payment service docs and deployment notes"
git push -u origin main
```

## Alur Deploy Saat Codebase Sudah Ada

Urutan aman yang disarankan:

1. pastikan perubahan lokal sudah `commit` dan `push`
2. masuk ke server dengan `ssh vps`
3. masuk ke `/var/www/payment/repo`
4. `git fetch origin && git pull origin main`
5. install/update dependency
6. update `.env` jika ada perubahan variable
7. jalankan migration jika memang ada perubahan schema
8. restart app service dan worker
9. verifikasi endpoint healthcheck
10. verifikasi webhook endpoint tidak error

## Bootstrap Server Untuk Repo Ini

Karena remote repo sudah diketahui, bootstrap di server menjadi:

```bash
ssh vps
sudo mkdir -p /var/www/payment/repo
sudo chown -R $USER:$USER /var/www/payment
git clone https://github.com/naevaid/payment.git /var/www/payment/repo
cd /var/www/payment/repo
```

## Validasi Setelah Deploy

Minimal lakukan pengecekan:

- endpoint utama aplikasi merespons normal
- endpoint callback Midtrans bisa diakses
- log tidak menunjukkan error boot
- queue worker aktif
- test charge dummy berhasil dibuat
- webhook test berhasil mengubah status transaksi
- forwarding callback ke client app tercatat

## Catatan Penting dari PRD

Poin-poin berikut dari `docs/PRD.md` harus dianggap wajib saat implementasi nanti:

- sistem bersifat multi-tenant berbasis `app_id`
- kredensial Midtrans harus terpusat
- webhook Midtrans harus diverifikasi signature-nya
- callback ke project asal harus async dan punya retry
- dashboard minimal perlu melihat sukses/gagal forwarding

## Next Step

Setelah codebase aplikasi mulai dibuat, file ini sebaiknya diperbarui agar memuat:

- stack aktual project
- perintah deploy yang benar-benar executable
- nama service process manager
- nama worker queue
- langkah rollback
- healthcheck URL
- lokasi log produksi
