# Integration Snippets

## Tujuan

Dokumen ini berisi contoh snippet integrasi yang bisa dijadikan titik awal oleh tim internal saat menghubungkan project ke `payment.naeva.id`.

Dokumen ini melengkapi `docs/API.md`:

- `API.md` fokus pada kontrak endpoint, request, response, dan error
- `INTEGRATION_SNIPPETS.md` fokus pada contoh implementasi di sisi client app

## Nilai yang Perlu Disiapkan

Setiap project minimal membutuhkan:

- `PAYMENT_BASE_URL`
- `PAYMENT_APP_ID`
- `PAYMENT_SECRET_KEY`
- `PAYMENT_CALLBACK_URL`

Contoh:

```env
PAYMENT_BASE_URL=https://payment.naeva.id
PAYMENT_APP_ID=project_a_prod
PAYMENT_SECRET_KEY=replace-with-real-secret
PAYMENT_CALLBACK_URL=https://project-a.naeva.id/api/payment/notification
```

## String to Sign

Semua contoh HMAC di bawah memakai format string yang sama:

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

## PHP Native Example

### Helper Signature

```php
<?php

function buildPaymentSignature(
    string $method,
    string $path,
    string $appId,
    string $timestamp,
    string $rawBody,
    string $secretKey
): string {
    $stringToSign = implode("\n", [
        strtoupper($method),
        $path,
        $appId,
        $timestamp,
        hash('sha256', $rawBody),
    ]);

    return hash_hmac('sha256', $stringToSign, $secretKey);
}
```

### Create Charge

```php
<?php

$baseUrl = getenv('PAYMENT_BASE_URL');
$appId = getenv('PAYMENT_APP_ID');
$secretKey = getenv('PAYMENT_SECRET_KEY');

$payload = [
    'order_id' => 'INV-PROJECTA-2026-001',
    'gross_amount' => 150000,
    'customer_details' => [
        'first_name' => 'Budi',
        'email' => 'budi@example.com',
    ],
    'custom_callback_url' => getenv('PAYMENT_CALLBACK_URL'),
    'metadata' => [
        'invoice_id' => 1001,
        'source' => 'project-a',
    ],
];

$path = '/api/v1/charge';
$timestamp = (string) time();
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$signature = buildPaymentSignature('POST', $path, $appId, $timestamp, $rawBody, $secretKey);

$ch = curl_init($baseUrl.$path);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-App-ID: '.$appId,
        'X-Timestamp: '.$timestamp,
        'X-Payment-Signature: '.$signature,
    ],
    CURLOPT_POSTFIELDS => $rawBody,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

var_dump($httpCode, $response);
```

### Transaction Lookup

```php
<?php

$query = http_build_query([
    'identifier' => 'INV-PROJECTA-2026-001',
    'by' => 'client_order_id',
]);

$path = '/api/v1/transactions/lookup?'.$query;
$timestamp = (string) time();
$rawBody = '';
$signature = buildPaymentSignature('GET', $path, $appId, $timestamp, $rawBody, $secretKey);

$ch = curl_init($baseUrl.$path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-App-ID: '.$appId,
        'X-Timestamp: '.$timestamp,
        'X-Payment-Signature: '.$signature,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

var_dump($httpCode, $response);
```

Catatan:

- Untuk request `GET`, query string harus ikut dimasukkan ke nilai `path` yang ditandatangani.
- Urutan query string harus sama dengan request akhir yang dikirim.

### Project Profile Readiness

```php
<?php

$path = '/api/v1/projects/me';
$timestamp = (string) time();
$rawBody = '';
$signature = buildPaymentSignature('GET', $path, $appId, $timestamp, $rawBody, $secretKey);

$ch = curl_init($baseUrl.$path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-App-ID: '.$appId,
        'X-Timestamp: '.$timestamp,
        'X-Payment-Signature: '.$signature,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

var_dump($httpCode, $response);
```

Gunakan endpoint ini untuk membaca:

- mode autentikasi aktif
- endpoint penting tenant
- status readiness integrasi
- retry callback dan delivery headers

## Laravel Example

### Service Class

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymentGatewayClient
{
    public function sign(string $method, string $path, string $rawBody, string $timestamp): string
    {
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            config('services.payment.app_id'),
            $timestamp,
            hash('sha256', $rawBody),
        ]);

        return hash_hmac(
            'sha256',
            $stringToSign,
            config('services.payment.secret_key'),
        );
    }

    public function charge(array $payload): array
    {
        $path = '/api/v1/charge';
        $timestamp = (string) now()->timestamp;
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::baseUrl(config('services.payment.base_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-App-ID' => config('services.payment.app_id'),
                'X-Timestamp' => $timestamp,
                'X-Payment-Signature' => $this->sign('POST', $path, $rawBody, $timestamp),
            ])
            ->post($path, $payload)
            ->throw();

        return $response->json();
    }

    public function lookupByClientOrderId(string $orderId): array
    {
        $query = http_build_query([
            'identifier' => $orderId,
            'by' => 'client_order_id',
        ]);

        $path = '/api/v1/transactions/lookup?'.$query;
        $timestamp = (string) now()->timestamp;

        $response = Http::baseUrl(config('services.payment.base_url'))
            ->acceptJson()
            ->withHeaders([
                'X-App-ID' => config('services.payment.app_id'),
                'X-Timestamp' => $timestamp,
                'X-Payment-Signature' => $this->sign('GET', $path, '', $timestamp),
            ])
            ->get($path)
            ->throw();

        return $response->json();
    }
}
```

### Contoh Pemakaian

```php
<?php

$payment = app(\App\Services\PaymentGatewayClient::class);

$response = $payment->charge([
    'order_id' => 'INV-PROJECTA-2026-001',
    'gross_amount' => 150000,
    'customer_details' => [
        'first_name' => 'Budi',
        'email' => 'budi@example.com',
    ],
    'custom_callback_url' => config('services.payment.callback_url'),
]);

// Simpan gateway_order_id dan redirect_url ke database project Anda.
```

### Ambil Readiness Profile

```php
<?php

$timestamp = (string) now()->timestamp;
$path = '/api/v1/projects/me';

$response = Http::baseUrl(config('services.payment.base_url'))
    ->acceptJson()
    ->withHeaders([
        'X-App-ID' => config('services.payment.app_id'),
        'X-Timestamp' => $timestamp,
        'X-Payment-Signature' => $payment->sign('GET', $path, '', $timestamp),
    ])
    ->get($path)
    ->throw()
    ->json();

// Cek $response['data']['readiness']['status'] sebelum pilot integration.
```

## Node.js Example

### Helper Signature

```js
const crypto = require('crypto');

function buildPaymentSignature({ method, path, appId, timestamp, rawBody, secretKey }) {
  const stringToSign = [
    method.toUpperCase(),
    path,
    appId,
    timestamp,
    crypto.createHash('sha256').update(rawBody).digest('hex'),
  ].join('\n');

  return crypto
    .createHmac('sha256', secretKey)
    .update(stringToSign)
    .digest('hex');
}
```

### Create Charge with Fetch

```js
const payload = {
  order_id: 'INV-PROJECTA-2026-001',
  gross_amount: 150000,
  customer_details: {
    first_name: 'Budi',
    email: 'budi@example.com',
  },
  custom_callback_url: process.env.PAYMENT_CALLBACK_URL,
};

const path = '/api/v1/charge';
const timestamp = Math.floor(Date.now() / 1000).toString();
const rawBody = JSON.stringify(payload);
const signature = buildPaymentSignature({
  method: 'POST',
  path,
  appId: process.env.PAYMENT_APP_ID,
  timestamp,
  rawBody,
  secretKey: process.env.PAYMENT_SECRET_KEY,
});

const response = await fetch(`${process.env.PAYMENT_BASE_URL}${path}`, {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-App-ID': process.env.PAYMENT_APP_ID,
    'X-Timestamp': timestamp,
    'X-Payment-Signature': signature,
  },
  body: rawBody,
});

const json = await response.json();
console.log(response.status, json);
```

## Verifikasi Callback dari payment.naeva.id

Saat project Anda menerima callback forwarding dari `payment.naeva.id`, verifikasi signature sebelum memproses update pembayaran.

Header callback yang relevan:

```text
X-Payment-App-Id: project_a_prod
X-Payment-Event: payment.status.updated
X-Payment-Attempt: 2
X-Payment-Timestamp: 1760832000
X-Payment-Delivery-Id: delivery-002
X-Payment-Signature: <signature>
```

Signature callback dibentuk dari JSON payload mentah menggunakan `secret_key` project.

### PHP Callback Verification

```php
<?php

$rawBody = file_get_contents('php://input');
$receivedSignature = $_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $rawBody, getenv('PAYMENT_SECRET_KEY'));

if (! hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid callback signature']);
    exit;
}

$payload = json_decode($rawBody, true);

// Lanjutkan proses update pembayaran di project Anda.
http_response_code(200);
echo json_encode(['status' => 'ok']);
```

### Laravel Callback Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request)
    {
        $rawBody = $request->getContent();
        $receivedSignature = (string) $request->header('X-Payment-Signature');
        $expectedSignature = hash_hmac(
            'sha256',
            $rawBody,
            (string) config('services.payment.secret_key'),
        );

        if (! hash_equals($expectedSignature, $receivedSignature)) {
            return response()->json([
                'message' => 'Invalid callback signature',
            ], 401);
        }

        $payload = $request->json()->all();

        // Cari order internal berdasarkan order_id atau gateway_order_id.
        // Update status pembayaran di aplikasi Anda.

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
```

### Node.js Callback Verification

```js
const crypto = require('crypto');

function verifyPaymentCallback(rawBody, receivedSignature, secretKey) {
  const expectedSignature = crypto
    .createHmac('sha256', secretKey)
    .update(rawBody)
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(expectedSignature),
    Buffer.from(receivedSignature)
  );
}
```

## Rekomendasi Implementasi Client App

- Simpan `gateway_order_id` setelah charge berhasil.
- Simpan juga `redirect_url`, `token`, dan `order_id` milik project Anda.
- Perlakukan callback sebagai sumber update status pembayaran yang paling penting.
- Buat proses update order yang idempotent agar callback/retry tidak menggandakan efek bisnis.
- Kembalikan response `2xx` secepat mungkin setelah signature valid dan data berhasil dicatat.

## Checklist Integrasi

- Dapatkan `app_id` dan `secret_key` dari dashboard `payment.naeva.id`
- Simpan kredensial di environment variable project Anda
- Panggil `GET /api/v1/projects/me` untuk verifikasi readiness integrasi tenant
- Implementasikan HMAC signing untuk request API
- Pastikan request `GET` dengan query string ikut menandatangani query string
- Buat endpoint callback di project asal
- Implementasikan verifikasi callback signature
- Simpan `gateway_order_id` hasil charge
- Siapkan lookup transaksi via `client_order_id` atau `gateway_order_id`
- Gunakan callback history untuk audit retry bila callback project tidak sampai
- Uji flow `charge -> bayar -> webhook -> callback forwarding`
- Pantau callback log di dashboard payment untuk validasi operasional
