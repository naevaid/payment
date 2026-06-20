# API Documentation

## Tujuan

Dokumen ini adalah panduan integrasi untuk tim internal Naeva yang ingin memakai layanan `payment.naeva.id` sebagai centralized payment gateway service.

Dokumen ini mengikuti implementasi yang sudah aktif saat ini. Jika ada perbedaan antara dokumen ini dan perilaku aplikasi, implementasi aplikasi menjadi acuan utama dan dokumen ini harus diperbarui.

Dokumen pendukung:

- `docs/INTEGRATION_SNIPPETS.md` untuk contoh implementasi client app

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

Response error standar tenant:

```json
{
  "message": "Invalid project request signature.",
  "error": {
    "code": "invalid_project_signature",
    "status": 401
  }
}
```

Response validasi:

```json
{
  "message": "The given data was invalid.",
  "error": {
    "code": "validation_failed",
    "status": 422,
    "details": {
      "errors": {
        "order_id": [
          "The order id field is required."
        ],
        "customer_details.first_name": [
          "The customer details.first name field is required."
        ]
      }
    }
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
    "is_active": true,
    "authentication": {
      "mode": "hmac_signature",
      "signature_algorithm": "sha256",
      "timestamp_tolerance_seconds": 300,
      "request_headers": {
        "app_id": "X-App-ID",
        "timestamp": "X-Timestamp",
        "signature": "X-Payment-Signature"
      },
      "legacy_secret_header": {
        "enabled": true,
        "header": "X-Secret-Key"
      }
    },
    "integration": {
      "base_url": "https://payment.naeva.id/api/v1",
      "environment": "production",
      "currency": "IDR",
      "endpoints": {
        "charge": "/api/v1/charge",
        "project_profile": "/api/v1/projects/me",
        "transaction_lookup": "/api/v1/transactions/lookup",
        "transaction_detail": "/api/v1/transactions/{gatewayOrderId}",
        "callback_history": "/api/v1/transactions/{gatewayOrderId}/callback-history"
      }
    },
    "callback": {
      "default_url": "https://project-a.naeva.id/payment/callback",
      "retry": {
        "queue": "payment-callbacks",
        "timeout_seconds": 10,
        "max_attempts": 3,
        "backoff_seconds": [60, 300, 900]
      },
      "delivery_headers": {
        "app_id": "X-Payment-App-Id",
        "event": "X-Payment-Event",
        "attempt": "X-Payment-Attempt",
        "timestamp": "X-Payment-Timestamp",
        "delivery_id": "X-Payment-Delivery-Id",
        "signature": "X-Payment-Signature"
      },
      "signature": {
        "algorithm": "sha256",
        "uses_project_secret_key": true
      }
    },
    "readiness": {
      "status": "ready",
      "can_charge": true,
      "has_default_callback_url": true,
      "checks": [
        {
          "name": "project_active",
          "passed": true,
          "message": "Project aktif dan dapat mengakses API tenant."
        },
        {
          "name": "default_callback_url_configured",
          "passed": true,
          "message": "Default callback URL sudah terpasang."
        },
        {
          "name": "hmac_signature_auth_ready",
          "passed": true,
          "message": "Gunakan HMAC signature untuk integrasi tenant yang disarankan."
        }
      ]
    }
  }
}
```

Catatan:

- `readiness.status` akan bernilai `action_required` jika ada konfigurasi penting yang belum siap, misalnya `default_callback_url` belum diatur.
- Endpoint ini adalah cara paling praktis untuk bootstrap integrasi tenant baru karena seluruh header auth, endpoint penting, dan retry callback diringkas di sini.

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

### 3. Lookup Transaction

- Method: `GET`
- Endpoint: `/api/v1/transactions/lookup`
- Auth: `HMAC` atau legacy fallback

#### Query Parameter

- `identifier`: wajib, nilai yang ingin dicari
- `by`: opsional, nilai `auto`, `gateway_order_id`, atau `client_order_id`

#### Catatan Signature GET

Untuk request `GET` dengan query string, path yang ditandatangani harus mencakup query string persis seperti request akhir.

Contoh:

```text
/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&by=client_order_id
```

#### Response 200

```json
{
  "data": {
    "gateway_order_id": "PROJECTLOOKUP-01JABC1234567890",
    "order_id": "INV-LOOKUP-001",
    "amount": 123000,
    "currency": "IDR",
    "status": "pending",
    "callback_status": "queued",
    "payment_type": "bank_transfer",
    "redirect_url": "https://midtrans.test/snap/lookup-001",
    "callback_url": "https://project-lookup.test/api/payment/callback",
    "metadata": {
      "invoice_id": 7001
    },
    "customer_details": {
      "first_name": "Lookup User",
      "email": "lookup@example.com"
    },
    "timestamps": {
      "created_at": "2026-06-20 02:30:00",
      "updated_at": "2026-06-20 02:30:00",
      "paid_at": null,
      "expires_at": null,
      "last_webhook_at": "2026-06-20 02:29:00"
    },
    "latest_webhook": {
      "status": "pending",
      "processing_status": "processed",
      "is_signature_valid": true,
      "received_at": "2026-06-20 02:29:00",
      "processed_at": "2026-06-20 02:29:00"
    },
    "latest_callback": {
      "attempt": 1,
      "event_type": "payment.status.updated",
      "callback_url": "https://project-lookup.test/api/payment/callback",
      "success": false,
      "response_status_code": 500,
      "error_message": "HTTP 500",
      "delivery_id": "delivery-lookup-001",
      "next_retry_at": "2026-06-20 02:31:00",
      "dispatched_at": "2026-06-20 02:30:30",
      "responded_at": "2026-06-20 02:30:31"
    }
  }
}
```

### 4. Get Transaction by Gateway Order ID

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
    "redirect_url": "https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz",
    "callback_url": "https://project-a.naeva.id/api/payment/notification",
    "metadata": {
      "invoice_id": 1001
    },
    "customer_details": {
      "first_name": "Budi"
    },
    "timestamps": {
      "created_at": "2026-06-20 02:25:00",
      "updated_at": "2026-06-20 02:30:00",
      "paid_at": null,
      "expires_at": null,
      "last_webhook_at": "2026-06-20 02:29:00"
    },
    "latest_webhook": {
      "status": "pending",
      "processing_status": "processed",
      "is_signature_valid": true,
      "received_at": "2026-06-20 02:29:00",
      "processed_at": "2026-06-20 02:29:00"
    },
    "latest_callback": {
      "attempt": 1,
      "event_type": "payment.status.updated",
      "callback_url": "https://project-a.naeva.id/api/payment/notification",
      "success": false,
      "response_status_code": 500,
      "error_message": "HTTP 500",
      "delivery_id": "delivery-001",
      "next_retry_at": "2026-06-20 02:31:00",
      "dispatched_at": "2026-06-20 02:30:30",
      "responded_at": "2026-06-20 02:30:31"
    }
  }
}
```

#### Response Error Umum

- `401 Unauthorized`: auth tidak valid
- `404 Not Found`: transaksi tidak ditemukan pada project yang sedang login

### 5. Get Callback Delivery History

- Method: `GET`
- Endpoint: `/api/v1/transactions/{gateway_order_id}/callback-history`
- Auth: `HMAC` atau legacy fallback

#### Query Parameter

- `limit`: opsional, integer, minimal `1`, maksimal `20`, default `5`

#### Response 200

```json
{
  "data": {
    "gateway_order_id": "PROJECTHISTORY-01JABC1234567890",
    "order_id": "INV-HISTORY-001",
    "callback_status": "queued",
    "history": [
      {
        "attempt": 3,
        "event_type": "payment.status.updated",
        "callback_url": "https://project-history.test/api/payment/callback",
        "success": true,
        "response_status_code": 200,
        "error_message": null,
        "delivery_id": "delivery-003",
        "next_retry_at": null,
        "dispatched_at": "2026-06-20 02:29:00",
        "responded_at": "2026-06-20 02:29:01"
      },
      {
        "attempt": 2,
        "event_type": "payment.status.updated",
        "callback_url": "https://project-history.test/api/payment/callback",
        "success": false,
        "response_status_code": 502,
        "error_message": "HTTP 502",
        "delivery_id": "delivery-002",
        "next_retry_at": "2026-06-20 02:35:00",
        "dispatched_at": "2026-06-20 02:28:00",
        "responded_at": "2026-06-20 02:28:01"
      }
    ]
  }
}
```

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
X-Payment-Attempt: 2
X-Payment-Timestamp: 1760832000
X-Payment-Delivery-Id: delivery-002
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

| HTTP | Code | Message | Arti |
| --- | --- | --- | --- |
| `401` | `missing_project_app_id` | `Missing project authentication app id header.` | Header `X-App-ID` tidak dikirim |
| `401` | `missing_project_hmac_headers` | `Missing project HMAC authentication headers.` | Header HMAC tidak lengkap |
| `401` | `invalid_project_timestamp` | `Invalid or expired project request timestamp.` | Timestamp di luar toleransi |
| `401` | `invalid_project_signature` | `Invalid project request signature.` | Signature HMAC tidak cocok |
| `401` | `missing_project_auth_headers` | `Missing project authentication headers.` | Header auth legacy/HMAC tidak lengkap |
| `401` | `invalid_project_credentials` | `Invalid project credentials.` | `app_id` atau secret tidak valid |
| `403` | `project_inactive` | `Project is inactive.` | Project ditemukan tetapi dinonaktifkan |
| `409` | `order_id_conflict` | `Order ID sudah pernah digunakan dengan payload yang berbeda.` | Request charge duplikat dengan payload berbeda |
| `404` | `resource_not_found` | `Resource not found.` | Transaksi/resource tidak ditemukan pada project aktif |
| `404` | `endpoint_not_found` | `Endpoint not found.` | Route API tenant tidak ada |
| `422` | `validation_failed` | `The given data was invalid.` | Field request tidak valid |

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

### Transaction Lookup by Client Order ID

```bash
curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&by=client_order_id" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>"
```

### Transaction Detail

```bash
curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>"
```

### Callback History

```bash
curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45/callback-history?limit=5" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: <signature>"
```

## Catatan Implementasi

- `gateway_order_id` dibuat oleh `payment.naeva.id` dan dipakai untuk relasi internal ke Midtrans.
- `order_id` tetap merepresentasikan ID order milik client app.
- Client app sebaiknya menyimpan `gateway_order_id` setelah `charge` berhasil untuk mempermudah lookup dan audit.
- Untuk request `GET` yang memakai query string, query string wajib ikut dimasukkan ke path yang ditandatangani.
- Gunakan `GET /projects/me` sebagai endpoint readiness awal saat provisioning integrasi tenant baru.
- Gunakan `GET /transactions/lookup` bila tenant lebih mudah mencari transaksi dengan `client_order_id`.
- Gunakan `GET /transactions/{gateway_order_id}/callback-history` untuk audit delivery callback ke project asal.
- Endpoint ini dirancang untuk server-to-server integration, bukan dipanggil langsung dari browser publik.
