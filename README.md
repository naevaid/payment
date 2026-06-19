# payment.naeva.id

Centralized payment gateway service untuk proyek-proyek internal Naeva.

## Tujuan

Project ini menjadi hub pembayaran terpusat yang:

- menerima request charge dari client app internal
- membuat token dan redirect pembayaran ke Midtrans
- menerima webhook dari Midtrans
- memperbarui status transaksi internal
- meneruskan callback status ke project asal secara async

## Dokumen Awal

- `docs/PRD.md`
- `DEPLOYMEN.md`

## Stack Rekomendasi Untuk VPS

Stack awal yang direkomendasikan agar cocok untuk VPS:

- Laravel
- PHP 8.3
- Nginx
- PostgreSQL atau MySQL
- Redis
- Supervisor

## Repository

Remote GitHub:

```text
https://github.com/naevaid/payment.git
```

## Alur Kerja yang Disarankan

1. kerjakan perubahan di lokal
2. commit dan push ke GitHub
3. deploy ke server dengan alias `ssh vps`
4. pull update terbaru di `/var/www/payment/repo`

## Catatan

Saat ini repository sudah memiliki bootstrap awal Laravel dan siap dilanjutkan ke fase implementasi API, webhook Midtrans, queue forwarding, dan dashboard internal.

## Bootstrap Saat Ini

- skeleton Laravel sudah ditambahkan ke root project
- remote git sudah diarahkan ke `https://github.com/naevaid/payment.git`
- deployment notes sudah menandai bahwa `payment.naeva.id` masih perlu SSL sertifikat sebelum production
- route dasar tersedia di `/` dan `/healthz`
