<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dokumentasi API - {{ config('app.name', 'payment') }}</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
        <style>
            :root {
                color-scheme: light;
                --bg: #f5f7fb;
                --surface: #ffffff;
                --surface-muted: #eef2ff;
                --surface-soft: #f8fafc;
                --text: #0f172a;
                --text-muted: #475569;
                --primary: #1d4ed8;
                --primary-dark: #1e40af;
                --border: #dbe2f0;
                --success-bg: #ecfdf5;
                --success-border: #a7f3d0;
                --success-text: #047857;
                --warning-bg: #fffbeb;
                --warning-border: #fde68a;
                --warning-text: #b45309;
                --shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: Arial, Helvetica, sans-serif;
                background:
                    radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 28%),
                    linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
                color: var(--text);
            }

            a {
                color: inherit;
            }

            code,
            pre {
                font-family: Consolas, "Courier New", monospace;
            }

            .shell {
                width: min(1180px, calc(100% - 32px));
                margin: 0 auto;
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 28px 0;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                text-decoration: none;
                color: inherit;
            }

            .brand-badge {
                width: 42px;
                height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                background: linear-gradient(135deg, #2563eb 0%, #0f172a 100%);
                color: #fff;
                font-weight: 700;
            }

            .brand-copy strong {
                display: block;
                font-size: 16px;
            }

            .brand-copy span {
                display: block;
                color: var(--text-muted);
                font-size: 13px;
                margin-top: 2px;
            }

            .nav-actions {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 44px;
                padding: 0 18px;
                border-radius: 12px;
                border: 1px solid var(--border);
                background: rgba(255, 255, 255, 0.78);
                color: var(--text);
                text-decoration: none;
                font-weight: 600;
                transition: 0.2s ease;
            }

            .button:hover {
                background: #fff;
                border-color: #cbd5e1;
            }

            .button-primary {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
                box-shadow: 0 12px 28px rgba(29, 78, 216, 0.22);
            }

            .button-primary:hover {
                background: var(--primary-dark);
                border-color: var(--primary-dark);
            }

            .hero {
                display: grid;
                gap: 24px;
                padding: 10px 0 24px;
            }

            .panel {
                background: rgba(255, 255, 255, 0.94);
                border: 1px solid rgba(219, 226, 240, 0.95);
                border-radius: 28px;
                box-shadow: var(--shadow);
            }

            .hero-copy {
                padding: 34px;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border-radius: 999px;
                background: var(--surface-muted);
                color: var(--primary-dark);
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }

            h1 {
                margin: 18px 0 12px;
                font-size: clamp(32px, 5vw, 52px);
                line-height: 1.06;
            }

            .lead {
                margin: 0;
                max-width: 860px;
                color: var(--text-muted);
                font-size: 18px;
                line-height: 1.75;
            }

            .meta-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 28px;
            }

            .meta-card {
                padding: 18px;
                border-radius: 20px;
                background: var(--surface-soft);
                border: 1px solid #e2e8f0;
            }

            .meta-card strong {
                display: block;
                margin-bottom: 8px;
            }

            .meta-card span {
                display: block;
                color: var(--text-muted);
                line-height: 1.6;
                font-size: 14px;
            }

            .layout {
                display: grid;
                grid-template-columns: 280px 1fr;
                gap: 24px;
                padding-bottom: 42px;
            }

            .sidebar {
                position: sticky;
                top: 24px;
                align-self: start;
                padding: 24px;
            }

            .sidebar h2,
            .content h2,
            .content h3 {
                margin-top: 0;
            }

            .sidebar ul {
                margin: 0;
                padding-left: 18px;
                color: var(--text-muted);
                line-height: 1.8;
            }

            .sidebar li + li {
                margin-top: 4px;
            }

            .content {
                padding: 32px;
            }

            .section {
                margin-bottom: 32px;
            }

            .section:last-child {
                margin-bottom: 0;
            }

            .section h2 {
                margin-bottom: 12px;
                font-size: 28px;
            }

            .section h3 {
                margin: 20px 0 10px;
                font-size: 19px;
            }

            .section p,
            .section li {
                color: var(--text-muted);
                line-height: 1.75;
            }

            .section ul {
                margin: 0;
                padding-left: 20px;
            }

            .section ul li + li {
                margin-top: 6px;
            }

            .callout {
                margin-top: 16px;
                padding: 16px 18px;
                border-radius: 18px;
                border: 1px solid var(--warning-border);
                background: var(--warning-bg);
                color: var(--warning-text);
                line-height: 1.7;
            }

            .success-box {
                margin-top: 16px;
                padding: 16px 18px;
                border-radius: 18px;
                border: 1px solid var(--success-border);
                background: var(--success-bg);
                color: var(--success-text);
                line-height: 1.7;
            }

            .code-block {
                margin: 14px 0 0;
                padding: 16px;
                border-radius: 20px;
                background: #0f172a;
                color: #e2e8f0;
                border: 1px solid #1e293b;
                overflow-x: auto;
                font-size: 13px;
                line-height: 1.7;
            }

            .inline-code {
                padding: 2px 7px;
                border-radius: 8px;
                background: #eff6ff;
                color: var(--primary-dark);
                font-size: 13px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
                overflow: hidden;
                border-radius: 18px;
                border: 1px solid var(--border);
            }

            th,
            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e2e8f0;
                text-align: left;
                vertical-align: top;
                font-size: 14px;
            }

            th {
                background: #f8fafc;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
            }

            tr:last-child td {
                border-bottom: 0;
            }

            .footer-copy {
                padding: 0 0 40px;
                color: var(--text-muted);
                font-size: 14px;
            }

            @media (max-width: 980px) {
                .layout {
                    grid-template-columns: 1fr;
                }

                .sidebar {
                    position: static;
                }

                .meta-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .topbar {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .nav-actions {
                    width: 100%;
                }

                .nav-actions .button {
                    flex: 1 1 auto;
                }

                .hero-copy,
                .sidebar,
                .content {
                    padding: 24px;
                }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <header class="topbar">
                <a class="brand" href="{{ route('home') }}">
                    <span class="brand-badge">N</span>
                    <span class="brand-copy">
                        <strong>payment.naeva.id</strong>
                        <span>Centralized payment gateway service</span>
                    </span>
                </a>

                <nav class="nav-actions">
                    <a class="button" href="{{ route('home') }}">Beranda</a>
                    @auth
                        <a class="button" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a class="button" href="{{ route('login') }}">Login</a>
                        <a class="button button-primary" href="{{ route('register') }}">Register</a>
                    @endauth
                </nav>
            </header>

            <main class="hero">
                <section class="panel hero-copy">
                    <span class="eyebrow">Dokumentasi API Publik</span>
                    <h1>Dokumentasi integrasi `payment.naeva.id` untuk seluruh project internal Naeva.</h1>
                    <p class="lead">
                        Halaman ini dapat diakses publik untuk mempermudah integrasi antar-server. Seluruh contoh di bawah
                        mengikuti implementasi yang aktif saat ini, termasuk autentikasi HMAC, readiness profile tenant,
                        lookup transaksi fleksibel, callback history, webhook Midtrans, dan callback forwarding ke project asal.
                    </p>

                    <div class="meta-grid">
                        <div class="meta-card">
                            <strong>Base URL</strong>
                            <span><code class="inline-code">https://payment.naeva.id/api/v1</code></span>
                        </div>
                        <div class="meta-card">
                            <strong>Auth Utama</strong>
                            <span>HMAC per request dengan <code class="inline-code">X-App-ID</code>, <code class="inline-code">X-Timestamp</code>, dan <code class="inline-code">X-Payment-Signature</code>.</span>
                        </div>
                        <div class="meta-card">
                            <strong>Callback</strong>
                            <span>Forwarding status pembayaran diproses async melalui queue, memiliki retry operasional, dan delivery metadata.</span>
                        </div>
                    </div>
                </section>

                <section class="layout">
                    <aside class="panel sidebar">
                        <h2>Daftar Isi</h2>
                        <ul>
                            <li>Base URL dan auth</li>
                            <li>Endpoint project profile readiness</li>
                            <li>Endpoint create charge</li>
                            <li>Endpoint transaction lookup</li>
                            <li>Endpoint callback history</li>
                            <li>Endpoint transaction detail</li>
                            <li>Webhook Midtrans</li>
                            <li>Callback forwarding</li>
                            <li>Status transaksi</li>
                            <li>Error reference</li>
                            <li>Contoh cURL</li>
                        </ul>
                    </aside>

                    <article class="panel content">
                        <section class="section">
                            <h2>Autentikasi Client App</h2>
                            <p>
                                Autentikasi yang disarankan adalah HMAC per request. Integrasi legacy dengan
                                <code class="inline-code">X-Secret-Key</code> masih bisa dipakai sementara selama mode migrasi
                                belum dimatikan.
                            </p>

                            <h3>Header utama</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Content-Type: application/json
Accept: application/json</code></pre>

                            <h3>String yang ditandatangani</h3>
                            <pre class="code-block"><code>{HTTP_METHOD}
{REQUEST_PATH}
{APP_ID}
{UNIX_TIMESTAMP}
{SHA256_RAW_BODY}</code></pre>

                            <h3>Contoh pembentukan signature</h3>
                            <pre class="code-block"><code>$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$bodyHash = hash('sha256', $rawBody);

$stringToSign = implode("\n", [
    'POST',
    '/api/v1/charge',
    'project_a_prod',
    $timestamp,
    $bodyHash,
]);

$signature = hash_hmac('sha256', $stringToSign, $secretKey);</code></pre>

                            <div class="callout">
                                Request timestamp harus berupa Unix timestamp dan saat ini diverifikasi dengan toleransi
                                sekitar 300 detik terhadap waktu server.
                            </div>
                        </section>

                        <section class="section">
                            <h2>GET /projects/me</h2>
                            <p>Dipakai untuk memverifikasi identitas project yang sedang terhubung sekaligus membaca readiness integrasi tenant.</p>

                            <h3>Header request</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Accept: application/json</code></pre>

                            <h3>Contoh request cURL</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url https://payment.naeva.id/api/v1/projects/me \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Example response 200</h3>
                            <pre class="code-block"><code>{
  "data": {
    "app_id": "project_a_prod",
    "project_name": "Project A",
    "default_callback_url": "https://client-app.example.com/payment/callback",
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
      "default_url": "https://client-app.example.com/payment/callback",
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
}</code></pre>

                            <div class="success-box">
                                Endpoint ini cocok dipanggil pertama kali saat provisioning integrasi baru, karena tenant bisa langsung membaca
                                header auth, endpoint penting, retry callback, dan status readiness dari satu response.
                            </div>
                        </section>

                        <section class="section">
                            <h2>POST /charge</h2>
                            <p>Endpoint utama untuk membuat transaksi baru dan mendapatkan token Snap Midtrans.</p>

                            <h3>Header request</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Content-Type: application/json
Accept: application/json</code></pre>

                            <h3>Example request body</h3>
                            <pre class="code-block"><code>{
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
  "custom_callback_url": "https://client-app.example.com/api/payment/notification",
  "metadata": {
    "invoice_id": 1001,
    "source": "project-a"
  }
}</code></pre>

                            <h3>Contoh request cURL</h3>
                            <pre class="code-block"><code>curl --request POST \
  --url https://payment.naeva.id/api/v1/charge \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;" \
  --data '{
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
    "custom_callback_url": "https://client-app.example.com/api/payment/notification",
    "metadata": {
      "invoice_id": 1001,
      "source": "project-a"
    }
  }'</code></pre>

                            <h3>Example response 201</h3>
                            <pre class="code-block"><code>{
  "status": "success",
  "project": {
    "app_id": "project_a_prod",
    "name": "Project A"
  },
  "order_id": "INV-PROJECTA-2026-001",
  "gateway_order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
  "token": "snap-token-xyz",
  "redirect_url": "https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz"
}</code></pre>

                            <h3>Field penting</h3>
                            <ul>
                                <li><code class="inline-code">order_id</code> wajib dan merepresentasikan ID order dari client app.</li>
                                <li><code class="inline-code">gross_amount</code> wajib, integer, minimal 1.</li>
                                <li><code class="inline-code">customer_details</code> wajib, minimal berisi <code class="inline-code">first_name</code>.</li>
                                <li><code class="inline-code">custom_callback_url</code> opsional untuk override callback URL default project.</li>
                                <li><code class="inline-code">expires_at</code> opsional dan harus berupa waktu setelah saat ini.</li>
                            </ul>
                        </section>

                        <section class="section">
                            <h2>GET /transactions/lookup</h2>
                            <p>Dipakai untuk mencari transaksi memakai <code class="inline-code">client_order_id</code>, <code class="inline-code">gateway_order_id</code>, atau mode <code class="inline-code">auto</code>.</p>

                            <h3>Header request</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Accept: application/json</code></pre>

                            <h3>Query parameter</h3>
                            <ul>
                                <li><code class="inline-code">identifier</code> wajib, nilai yang ingin dicari.</li>
                                <li><code class="inline-code">by</code> opsional, nilai <code class="inline-code">auto</code>, <code class="inline-code">gateway_order_id</code>, atau <code class="inline-code">client_order_id</code>.</li>
                            </ul>

                            <h3>Contoh request cURL</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&amp;by=client_order_id" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Example response 200</h3>
                            <pre class="code-block"><code>{
  "data": {
    "gateway_order_id": "PROJECTLOOKUP-01JABC1234567890",
    "order_id": "INV-LOOKUP-001",
    "amount": 123000,
    "currency": "IDR",
    "status": "pending",
    "callback_status": "queued",
    "payment_type": "bank_transfer",
    "redirect_url": "https://midtrans.test/snap/lookup-001",
    "callback_url": "https://client-app.example.com/api/payment/callback",
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
      "callback_url": "https://client-app.example.com/api/payment/callback",
      "success": false,
      "response_status_code": 500,
      "error_message": "HTTP 500",
      "delivery_id": "delivery-lookup-001",
      "next_retry_at": "2026-06-20 02:31:00",
      "dispatched_at": "2026-06-20 02:30:30",
      "responded_at": "2026-06-20 02:30:31"
    }
  }
}</code></pre>

                            <div class="callout">
                                Untuk request <code class="inline-code">GET</code> yang memakai query string, path yang ditandatangani
                                harus mencakup query string persis seperti request akhir. Contoh:
                                <code class="inline-code">/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&amp;by=client_order_id</code>
                            </div>
                        </section>

                        <section class="section">
                            <h2>GET /transactions/{gateway_order_id}</h2>
                            <p>Dipakai untuk mengambil detail transaksi lengkap berdasarkan gateway order id yang dibuat oleh layanan payment.</p>

                            <h3>Header request</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Accept: application/json</code></pre>

                            <h3>Parameter path</h3>
                            <ul>
                                <li><code class="inline-code">gateway_order_id</code> wajib, ID transaksi internal yang dikembalikan saat endpoint <code class="inline-code">POST /charge</code> berhasil.</li>
                            </ul>

                            <h3>Contoh request cURL</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Example response 200</h3>
                            <pre class="code-block"><code>{
  "data": {
    "gateway_order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
    "order_id": "INV-PROJECTA-2026-001",
    "amount": 150000,
    "currency": "IDR",
    "status": "pending",
    "callback_status": "queued",
    "payment_type": "bank_transfer",
    "redirect_url": "https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz",
    "callback_url": "https://client-app.example.com/api/payment/notification",
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
      "callback_url": "https://client-app.example.com/api/payment/notification",
      "success": false,
      "response_status_code": 500,
      "error_message": "HTTP 500",
      "delivery_id": "delivery-001",
      "next_retry_at": "2026-06-20 02:31:00",
      "dispatched_at": "2026-06-20 02:30:30",
      "responded_at": "2026-06-20 02:30:31"
    }
  }
}</code></pre>
                        </section>

                        <section class="section">
                            <h2>GET /transactions/{gateway_order_id}/callback-history</h2>
                            <p>Dipakai untuk audit seluruh riwayat delivery callback per transaksi, termasuk retry terbaru.</p>

                            <h3>Header request</h3>
                            <pre class="code-block"><code>X-App-ID: project_a_prod
X-Timestamp: 1760832000
X-Payment-Signature: &lt;hmac_signature&gt;
Accept: application/json</code></pre>

                            <h3>Query parameter</h3>
                            <ul>
                                <li><code class="inline-code">limit</code> opsional, integer 1-20, default <code class="inline-code">5</code>.</li>
                            </ul>

                            <h3>Contoh request cURL</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45/callback-history?limit=5" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Example response 200</h3>
                            <pre class="code-block"><code>{
  "data": {
    "gateway_order_id": "PROJECTHISTORY-01JABC1234567890",
    "order_id": "INV-HISTORY-001",
    "callback_status": "queued",
    "history": [
      {
        "attempt": 3,
        "event_type": "payment.status.updated",
        "callback_url": "https://client-app.example.com/api/payment/callback",
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
        "callback_url": "https://client-app.example.com/api/payment/callback",
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
}</code></pre>
                        </section>

                        <section class="section">
                            <h2>Webhook Midtrans</h2>
                            <p>
                                Endpoint <code class="inline-code">POST /api/v1/callback/midtrans</code> digunakan oleh Midtrans.
                                Endpoint ini memverifikasi signature webhook, mencari transaksi internal, memperbarui status, lalu
                                menjadwalkan callback async ke project asal.
                            </p>

                            <div class="callout">
                                URL yang bisa dipakai pada konfigurasi Midtrans:
                                <code class="inline-code">Notification URL = https://payment.naeva.id/api/v1/callback/midtrans</code>
                                dan
                                <code class="inline-code">Finish Redirect URL = https://payment.naeva.id/midtrans/finish</code>.
                            </div>

                            <h3>Contoh payload webhook dari Midtrans</h3>
                            <pre class="code-block"><code>{
  "transaction_time": "2026-06-20 14:15:13",
  "transaction_status": "settlement",
  "transaction_id": "513f1f01-c9da-474c-9fc9-d5c64364b709",
  "status_message": "midtrans payment notification",
  "status_code": "200",
  "signature_key": "&lt;midtrans_signature&gt;",
  "settlement_time": "2026-06-20 14:16:13",
  "payment_type": "gopay",
  "order_id": "PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45",
  "merchant_id": "M351033033",
  "gross_amount": "150000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}</code></pre>

                            <h3>Example response invalid signature</h3>
                            <pre class="code-block"><code>{
  "message": "Invalid signature."
}</code></pre>

                            <h3>Example response reachability check</h3>
                            <pre class="code-block"><code>{
  "ok": true,
  "message": "Midtrans notification endpoint is reachable."
}</code></pre>

                            <h3>Example response accepted</h3>
                            <pre class="code-block"><code>{
  "status": "accepted"
}</code></pre>

                            <h3>Example response ignored test notification</h3>
                            <pre class="code-block"><code>{
  "ok": true,
  "message": "Midtrans notification endpoint is reachable.",
  "ignored": true
}</code></pre>
                        </section>

                        <section class="section">
                            <h2>Callback Forwarding ke Project Asal</h2>
                            <p>
                                Setelah webhook Midtrans valid diproses, <code class="inline-code">payment.naeva.id</code> akan
                                mengirim callback ke <code class="inline-code">custom_callback_url</code> atau
                                <code class="inline-code">default_callback_url</code> project.
                            </p>

                            <h3>Example request method dan target</h3>
                            <pre class="code-block"><code>POST https://client-app.example.com/api/payment/callback</code></pre>

                            <h3>Header callback</h3>
                            <pre class="code-block"><code>User-Agent: Naeva-Payment-Callback/1.0
X-Payment-App-Id: project_a_prod
X-Payment-Event: payment.status.updated
X-Payment-Attempt: 2
X-Payment-Timestamp: 1760832000
X-Payment-Delivery-Id: delivery-002
X-Payment-Signature: &lt;hmac_sha256_payload_signature&gt;
Content-Type: application/json
Accept: application/json</code></pre>

                            <h3>Contoh request mentah callback test</h3>
                            <p>
                                Request ini dipakai saat tombol <code class="inline-code">Test Callback URL</code> dijalankan dari dashboard project.
                            </p>
                            <pre class="code-block"><code>POST /api/payment/callback HTTP/1.1
Host: client-app.example.com
User-Agent: Naeva-Payment-Callback/1.0
X-Payment-App-Id: APP-FI4YVWSGZHXN
X-Payment-Event: payment.callback.test
X-Payment-Attempt: 1
X-Payment-Timestamp: 1760860800
X-Payment-Delivery-Id: 7d9d1f42-4d4c-4a47-a5e7-9f4050cc91d0
X-Payment-Signature: a13bcceff73a921a3e709ff52b9148f1944067ef81e7b40a320c388953ed875e
Content-Type: application/json
Accept: application/json
Content-Length: 278

{"test":true,"event":"payment.callback.test","message":"This is a callback connectivity test from payment.naeva.id","app_id":"APP-FI4YVWSGZHXN","project_name":"LevelUP adsPRO","callback_url":"https://client-app.example.com/api/payment/callback","sent_at":"2026-06-20 15:00:00"}</code></pre>

                            <h3>Raw body callback test</h3>
                            <p>
                                Signature di header dihitung dari body mentah ini, persis apa adanya tanpa tambahan spasi atau format ulang.
                            </p>
                            <pre class="code-block"><code>{"test":true,"event":"payment.callback.test","message":"This is a callback connectivity test from payment.naeva.id","app_id":"APP-FI4YVWSGZHXN","project_name":"LevelUP adsPRO","callback_url":"https://client-app.example.com/api/payment/callback","sent_at":"2026-06-20 15:00:00"}</code></pre>

                            <h3>Payload callback</h3>
                            <pre class="code-block"><code>{
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
}</code></pre>

                            <h3>Contoh request mentah callback status transaksi</h3>
                            <pre class="code-block"><code>POST /api/payment/callback HTTP/1.1
Host: client-app.example.com
User-Agent: Naeva-Payment-Callback/1.0
X-Payment-App-Id: APP-FI4YVWSGZHXN
X-Payment-Event: payment.status.updated
X-Payment-Attempt: 2
X-Payment-Timestamp: 1760859000
X-Payment-Delivery-Id: delivery-002
X-Payment-Signature: d52b708685994945aac1213abb8b22b973ff1ffcd3e76f32f92fac387f2f32b1
Content-Type: application/json
Accept: application/json
Content-Length: 284

{"order_id":"INV-PROJECTA-2026-001","gateway_order_id":"PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45","transaction_status":"settlement","payment_type":"bank_transfer","gross_amount":150000,"transaction_time":"2026-06-20 14:30:00","metadata":{"invoice_id":1001,"source":"project-a"}}</code></pre>

                            <h3>Raw body callback status transaksi</h3>
                            <pre class="code-block"><code>{"order_id":"INV-PROJECTA-2026-001","gateway_order_id":"PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45","transaction_status":"settlement","payment_type":"bank_transfer","gross_amount":150000,"transaction_time":"2026-06-20 14:30:00","metadata":{"invoice_id":1001,"source":"project-a"}}</code></pre>

                            <h3>Example response dari endpoint project</h3>
                            <pre class="code-block"><code>{
  "received": true,
  "message": "Callback diterima."
}</code></pre>

                            <h3>Contoh verifikasi signature di endpoint project</h3>
                            <pre class="code-block"><code>$rawBody = $request->getContent();
$expectedSignature = hash_hmac('sha256', $rawBody, $projectSecretKey);
$receivedSignature = (string) $request->header('X-Payment-Signature');

if (! hash_equals($expectedSignature, $receivedSignature)) {
    abort(401, 'Signature callback payment tidak valid.');
}</code></pre>

                            <div class="success-box">
                                Callback dengan response HTTP `2xx` dianggap sukses. Response non-`2xx` atau network failure
                                akan masuk retry async sesuai backoff operasional yang aktif di service.
                            </div>
                        </section>

                        <section class="section">
                            <h2>Status dan Error</h2>
                            <h3>Status transaksi</h3>
                            <ul>
                                <li><code class="inline-code">pending</code></li>
                                <li><code class="inline-code">settlement</code></li>
                                <li><code class="inline-code">failed</code></li>
                                <li><code class="inline-code">expired</code></li>
                                <li><code class="inline-code">cancelled</code></li>
                                <li><code class="inline-code">refunded</code></li>
                            </ul>

                            <h3>Status callback</h3>
                            <ul>
                                <li><code class="inline-code">pending</code></li>
                                <li><code class="inline-code">queued</code></li>
                                <li><code class="inline-code">success</code></li>
                                <li><code class="inline-code">failed</code></li>
                                <li><code class="inline-code">skipped</code></li>
                            </ul>

                            <h3>Error umum</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>HTTP</th>
                                        <th>Code</th>
                                        <th>Message</th>
                                        <th>Arti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>401</td>
                                        <td><code>missing_project_app_id</code></td>
                                        <td><code>Missing project authentication app id header.</code></td>
                                        <td>Header <code class="inline-code">X-App-ID</code> tidak dikirim.</td>
                                    </tr>
                                    <tr>
                                        <td>401</td>
                                        <td><code>missing_project_hmac_headers</code></td>
                                        <td><code>Missing project HMAC authentication headers.</code></td>
                                        <td>Header HMAC tidak lengkap.</td>
                                    </tr>
                                    <tr>
                                        <td>401</td>
                                        <td><code>invalid_project_timestamp</code></td>
                                        <td><code>Invalid or expired project request timestamp.</code></td>
                                        <td>Timestamp di luar toleransi.</td>
                                    </tr>
                                    <tr>
                                        <td>401</td>
                                        <td><code>invalid_project_signature</code></td>
                                        <td><code>Invalid project request signature.</code></td>
                                        <td>Signature HMAC tidak cocok.</td>
                                    </tr>
                                    <tr>
                                        <td>401</td>
                                        <td><code>invalid_project_credentials</code></td>
                                        <td><code>Invalid project credentials.</code></td>
                                        <td><code class="inline-code">app_id</code> atau secret tidak valid.</td>
                                    </tr>
                                    <tr>
                                        <td>403</td>
                                        <td><code>project_inactive</code></td>
                                        <td><code>Project is inactive.</code></td>
                                        <td>Project ditemukan tetapi sedang nonaktif.</td>
                                    </tr>
                                    <tr>
                                        <td>409</td>
                                        <td><code>order_id_conflict</code></td>
                                        <td><code>Order ID sudah pernah digunakan dengan payload yang berbeda.</code></td>
                                        <td>Charge duplikat dikirim dengan payload berbeda.</td>
                                    </tr>
                                    <tr>
                                        <td>404</td>
                                        <td><code>resource_not_found</code></td>
                                        <td><code>Resource not found.</code></td>
                                        <td>Resource transaksi tidak ditemukan pada project aktif.</td>
                                    </tr>
                                    <tr>
                                        <td>404</td>
                                        <td><code>endpoint_not_found</code></td>
                                        <td><code>Endpoint not found.</code></td>
                                        <td>Route API tenant tidak tersedia.</td>
                                    </tr>
                                    <tr>
                                        <td>403</td>
                                        <td>-</td>
                                        <td><code>Invalid signature.</code></td>
                                        <td>Signature webhook Midtrans tidak valid.</td>
                                    </tr>
                                    <tr>
                                        <td>422</td>
                                        <td><code>validation_failed</code></td>
                                        <td><code>The given data was invalid.</code></td>
                                        <td>Payload request tidak lolos validasi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        <section class="section">
                            <h2>Contoh cURL</h2>
                            <h3>Create charge</h3>
                            <pre class="code-block"><code>curl --request POST \
  --url https://payment.naeva.id/api/v1/charge \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;" \
  --data '{
    "order_id": "INV-PROJECTA-2026-001",
    "gross_amount": 150000,
    "customer_details": {
      "first_name": "Budi",
      "email": "budi@example.com"
    }
  }'</code></pre>

                            <h3>Project profile</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url https://payment.naeva.id/api/v1/projects/me \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Transaction lookup</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&amp;by=client_order_id" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>

                            <h3>Callback history</h3>
                            <pre class="code-block"><code>curl --request GET \
  --url "https://payment.naeva.id/api/v1/transactions/PROJECT-A-PROD-01JY3G0T2T8V40Q0V4K2QJ8G45/callback-history?limit=5" \
  --header "Accept: application/json" \
  --header "X-App-ID: project_a_prod" \
  --header "X-Timestamp: 1760832000" \
  --header "X-Payment-Signature: &lt;signature&gt;"</code></pre>
                        </section>
                    </article>
                </section>
            </main>

            <footer class="footer-copy">
                Dokumen publik ini mengikuti implementasi aktif pada service Payment. Untuk kebutuhan internal dan sumber lengkap,
                file referensi tetap tersedia di repository pada `docs/API.md`.
            </footer>
        </div>
    </body>
</html>
