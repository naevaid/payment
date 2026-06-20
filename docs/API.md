# API Documentation

## Tujuan

Dokumen ini adalah panduan integrasi untuk tim internal Naeva yang ingin memakai layanan `payment.naeva.id` sebagai centralized payment gateway service.

Dokumen ini mengikuti implementasi yang sudah aktif saat ini. Jika ada perbedaan antara dokumen ini dan perilaku aplikasi, implementasi aplikasi menjadi acuan utama dan dokumen ini harus diperbarui.

## Base URL

- Production: `https://payment.naeva.id`
- API prefix: `/api/v1`

Contoh endpoint penuh:

- `POST https://payment.naeva.id/api/v1/charge`
- `GET https://payment.naeva.id/api/v1/projects/me`
- `GET https://payment.naeva.id/api/v1/transactions/{gateway_order_id}`

## Autentikasi Client App

Autentikasi utama yang disarankan adalah HMAC per request.

Header utama:

```text
X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: <hmac_signature>
Content-Type: application/json
Accept: application/json
```

### String yang Ditandatangani

Signature dibentuk dari string berikut:

```text
{HTTP_METHOD}
{REQUEST_PATH}
{APP_ID}
{UNIX_TIMESTAMP}
{SHA256_RAW_BODY}
```

Contoh:

```text
POST
/api/v1/charge
project_a_prod
1760832000
8d879cfad11309b33156da7d68b4e5cdca8e03ae72dcee9d7df8a37f06787423
```

Lalu string di atas ditandatangani dengan:

- algoritma: `HMAC-SHA256`
- secret: `secret_key` milik project

### Contoh Pseudocode HMAC

```php
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$bodyHash = hash('sha256', $rawBody);

$stringToSign = implode("\n", [
    'POST',
    '/api/v1/charge',
    'project_a_prod',
    $timestamp,
    $bodyHash,
]);

$signature = hash_hmac('sha256', $stringToSign, $secretKey);
```

### Timestamp Tolerance

- Request timestamp harus berupa Unix timestamp.
- Toleransi saat ini: `300` detik.
- Request dengan timestamp terlalu lama atau terlalu jauh dari waktu server akan ditolak.

### Fallback Legacy

Untuk masa migrasi, sistem masih dapat menerima header berikut jika mode legacy masih diizinkan:

```text
X-App-ID: project_a_prod
X-Secret-Key: secret_abc123
```

Catatan:

- mode ini hanya fallback sementara
- integrasi baru sebaiknya langsung memakai HMAC

## Format Response

Semua endpoint API merespons JSON.

Response sukses biasanya berbentuk:

```json
{
  "status": "success"
}
```

atau:

```json
{
  "data": {
    "key": "value"
  }
}
```

Response error umum:

```json
{
  "message": "Invalid project request signature."
}
```

Response validasi Laravel:

```json
{
  "message": "The customer details field is required. (and 1 more error)",
  "errors": {
    "customer_details": [
      "The customer details field is required."
    ]
  }
}
```

## Endpoint

### 1. Get Project Profile

- Method: `GET`
- Endpoint: `/api/v1/projects/me`
- Auth: `HMAC` atau legacy fallback

#### Header

```text
X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: <hmac_signature>
Accept: application/json
```

#### Response 200

```json
{
  "data": {
    "app_id": "project_a_prod",
    "project_name": "Project A",
    "default_callback_url": "https://project-a.naeva.id/payment/callback",
    "is_active": true
  }
}
```

### 2. Create Charge

- Method: `POST`
- Endpoint: `/api/v1/charge`
- Auth: `HMAC` atau legacy fallback

#### Header

```text
X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: <hmac_signature>
Content-Type: application/json
Accept: application/json
```

#### Request Body

```json
{
  "order_id": "INV-PROJECTA-2026-001",
  "gross_amount": 150000,
  "currency": "IDR",
  "customer_details": {
    "first_name": "Budi",
    "last_name": "Santoso",
    "email": "budi@example.com",
    "phone": "081234567890"
  },
  "item_details": [
    {
      "id": "SKU-INV-001",
      "price": 150000,
      "quantity": 1,
      "name": "Invoice Payment"
    }
  ],
  "custom_callback_url": "https://project-a.naeva.id/api/payment/notification",
  "metadata": {
    "invoice_id": 1001,
    "source": "project-a"
  }
}
```

#### Field Request

- `order_id`: wajib, ID order dari client app
- `gross_amount`: wajib, integer, minimal `1`
- `currency`: opsional, default `IDR`
- `customer_details`: wajib
- `customer_details.first_name`: wajib
- `customer_details.last_name`: opsional
- `customer_details.email`: opsional
- `customer_details.phone`: opsional
- `item_details`: opsional, array
- `custom_callback_url`: opsional, override callback URL project
- `metadata`: opsional, object bebas
- `expires_at`: opsional, format tanggal valid dan harus setelah waktu saat ini

#### Response 201

```json
{
  "status": "success",
  "project": {
    "app_id": "project_a_prod",
    "name": "Project A"
  },
  "order_id": "INV-PROJECTA-2026-001",
  "gateway_order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
  "token": "snap-token-xyz",
  "redirect_url": "https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz"
}
```

#### Response Error Umum

- `401 Unauthorized`: app id, signature, timestamp, atau secret tidak valid
- `422 Unprocessable Entity`: payload request tidak lolos validasi
- `500 Internal Server Error`: konfigurasi Midtrans tidak lengkap atau error upstream

### 3. Get Transaction by Gateway Order ID

- Method: `GET`
- Endpoint: `/api/v1/transactions/{gateway_order_id}`
- Auth: `HMAC` atau legacy fallback

#### Header

```text
X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: <hmac_signature>
Accept: application/json
```

#### Response 200

```json
{
  "data": {
    "gateway_order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
    "order_id": "INV-PROJECTA-2026-001",
    "amount": 150000,
    "currency": "IDR",
    "status": "pending",
    "callback_status": "queued",
    "payment_type": "bank_transfer",
    "redirect_url": "https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz"
  }
}
```

#### Response Error Umum

- `401 Unauthorized`: auth tidak valid
- `404 Not Found`: transaksi tidak ditemukan pada project yang sedang login

## Status Transaksi

Nilai `status` yang saat ini digunakan:

- `pending`
- `settlement`
- `failed`
- `expired`
- `cancelled`
- `refunded`

Nilai `callback_status` yang saat ini digunakan:

- `pending`
- `queued`
- `success`
- `failed`
- `skipped`

## Webhook Midtrans

Endpoint ini dipakai Midtrans, bukan client app internal.

- Method: `POST`
- Endpoint: `/api/v1/callback/midtrans`

Perilaku:

- memverifikasi `signature_key` dari Midtrans
- mencari transaksi berdasarkan `order_id` yang dikirim Midtrans
- memperbarui status transaksi internal
- menjadwalkan callback forwarding async ke endpoint project asal

### Response Jika Signature Invalid

```json
{
  "message": "Invalid signature."
}
```

Status: `403`

### Response Jika Transaksi Tidak Ditemukan

```json
{
  "message": "Transaction not found."
}
```

Status: `404`

### Response Accepted

```json
{
  "status": "accepted"
}
```

Status: `200`

## Callback Forwarding ke Client App

Setelah webhook Midtrans valid diproses, `payment.naeva.id` akan mengirim callback ke:

- `custom_callback_url` dari request charge, jika ada
- jika tidak ada, `default_callback_url` milik project

### Header Callback yang Dikirim

```text
User-Agent: Naeva-Payment-Callback/1.0
X-Payment-App-Id: project_a_prod
X-Payment-Event: payment.status.updated
X-Payment-Signature: <hmac_sha256_payload_signature>
Content-Type: application/json
Accept: application/json
```

### Payload Callback

```json
{
  "order_id": "INV-PROJECTA-2026-001",
  "gateway_order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
  "transaction_status": "settlement",
  "payment_type": "bank_transfer",
  "gross_amount": 150000,
  "transaction_time": "2026-06-20 02:30:00",
  "metadata": {
    "invoice_id": 1001,
    "source": "project-a"
  }
}
```

### Verifikasi Signature Callback

Signature callback dibentuk dari JSON payload mentah dengan secret key project:

```php
$signature = hash_hmac(
    'sha256',
    json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    $projectSecretKey
);
```

### Ekspektasi Response dari Client App

- status `2xx` dianggap sukses
- status non-`2xx` dianggap gagal dan akan masuk mekanisme retry

### Retry Callback

Saat ini callback async memakai retry backoff:

- attempt 1 -> delay `60` detik
- attempt 2 -> delay `300` detik
- attempt 3 -> delay `900` detik

Jika semua retry gagal, status callback akan tetap tercatat pada log operasional dashboard.

## Error Reference

Error umum yang saat ini mungkin ditemui:

| HTTP | Message | Arti |
| --- | --- | --- |
| `401` | `Missing project authentication app id header.` | Header `X-App-ID` tidak dikirim |
| `401` | `Missing project HMAC authentication headers.` | Header HMAC tidak lengkap |
| `401` | `Invalid or expired project request timestamp.` | Timestamp di luar toleransi |
| `401` | `Invalid project request signature.` | Signature HMAC tidak cocok |
| `401` | `Missing project authentication headers.` | Header auth legacy/HMAC tidak lengkap |
| `401` | `Invalid project credentials.` | `app_id` atau secret tidak valid |
| `403` | `Invalid signature.` | Signature webhook Midtrans tidak valid |
| `404` | `Transaction not found.` | Transaksi tidak ditemukan |
| `422` | Laravel validation error | Field request tidak valid |

## Contoh cURL

### Charge dengan HMAC

```bash
curl --request POST \
  --url https://payment.naeva.id/api/v1/charge \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>" \
  --data '{
    "order_id": "INV-PROJECTA-2026-001",
    "gross_amount": 150000,
    "customer_details": {
      "first_name": "Budi",
      "email": "budi@example.com"
    }
  }'
```

### Project Profile

```bash
curl --request GET \
  --url https://payment.naeva.id/api/v1/projects/me \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>"
```

### Transaction Lookup

```bash
curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>"
```

## Catatan Implementasi

- `gateway_order_id` dibuat oleh `payment.naeva.id` dan dipakai untuk relasi internal ke Midtrans.
- `order_id` tetap merepresentasikan ID order milik client app.
- Client app sebaiknya menyimpan `gateway_order_id` setelah `charge` berhasil untuk mempermudah lookup dan audit.
- Endpoint ini dirancang untuk server-to-server integration, bukan dipanggil langsung dari browser publik.
