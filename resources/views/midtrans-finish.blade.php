<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Status Transaksi - {{ config('app.name', 'payment') }}</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f4f7fb;
                --surface: #ffffff;
                --text: #0f172a;
                --muted: #475569;
                --border: #dbe2f0;
                --primary: #1d4ed8;
                --primary-dark: #1e40af;
                --success-bg: #ecfdf5;
                --success-border: #a7f3d0;
                --success-text: #047857;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                font-family: Arial, Helvetica, sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top right, rgba(37, 99, 235, 0.14), transparent 28%),
                    linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            }

            .card {
                width: min(100%, 680px);
                padding: 32px;
                border: 1px solid var(--border);
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.96);
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                min-height: 32px;
                padding: 0 12px;
                border-radius: 999px;
                background: #dbeafe;
                color: var(--primary-dark);
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }

            h1 {
                margin: 18px 0 12px;
                font-size: clamp(30px, 4vw, 42px);
                line-height: 1.08;
            }

            p {
                margin: 0;
                color: var(--muted);
                line-height: 1.7;
            }

            .notice {
                margin-top: 22px;
                padding: 16px 18px;
                border: 1px solid var(--success-border);
                border-radius: 18px;
                background: var(--success-bg);
                color: var(--success-text);
                line-height: 1.6;
            }

            .meta {
                margin-top: 22px;
                display: grid;
                gap: 12px;
            }

            .meta-item {
                padding: 14px 16px;
                border: 1px solid var(--border);
                border-radius: 16px;
                background: var(--surface);
            }

            .meta-item strong {
                display: block;
                margin-bottom: 6px;
                font-size: 13px;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .meta-item code {
                font-family: Consolas, "Courier New", monospace;
                font-size: 14px;
                word-break: break-all;
            }

            .actions {
                margin-top: 24px;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0 16px;
                border-radius: 14px;
                border: 1px solid var(--border);
                text-decoration: none;
                font-weight: 600;
                color: inherit;
                background: #fff;
            }

            .button-primary {
                border-color: var(--primary);
                background: var(--primary);
                color: #fff;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <span class="eyebrow">Status Pembayaran</span>
            <h1>Status transaksi</h1>
            <p>
                Halaman ini dipakai sebagai landing page setelah customer menyelesaikan alur pembayaran. Status akhir pembayaran tetap
                dipastikan melalui notifikasi server-to-server dan sinkronisasi transaksi di service ini.
            </p>

            <div class="notice">
                Halaman ini sengaja dibuat fleksibel agar bisa menampilkan ringkasan status, detail transaksi lokal, dan informasi
                yang dibawa oleh redirect pembayaran dalam satu tampilan.
            </div>

            <div class="meta">
                @if ($transaction)
                    <div class="meta-item">
                        <strong>Gateway Order ID</strong>
                        <code>{{ $transaction->gateway_order_id }}</code>
                    </div>
                @endif

                @if ($transaction?->client_order_id)
                    <div class="meta-item">
                        <strong>Order ID Tenant</strong>
                        <code>{{ $transaction->client_order_id }}</code>
                    </div>
                @endif

                @if ($transaction)
                    <div class="meta-item">
                        <strong>Status Internal</strong>
                        <code>{{ $transaction->status->value }}</code>
                    </div>

                    <div class="meta-item">
                        <strong>Status Callback</strong>
                        <code>{{ $transaction->callback_status->value }}</code>
                    </div>

                    <div class="meta-item">
                        <strong>Nominal</strong>
                        <code>Rp {{ number_format($transaction->amount, 0, ',', '.') }} {{ $transaction->currency }}</code>
                    </div>

                    <div class="meta-item">
                        <strong>Project</strong>
                        <code>{{ $transaction->project?->project_name ?? '-' }} ({{ $transaction->project?->app_id ?? '-' }})</code>
                    </div>

                    <div class="meta-item">
                        <strong>Terakhir Diperbarui</strong>
                        <code>{{ $transaction->updated_at?->format('d M Y H:i:s') ?? '-' }}</code>
                    </div>
                @endif

                @if ($orderId !== '')
                    <div class="meta-item">
                        <strong>Order ID dari Redirect</strong>
                        <code>{{ $orderId }}</code>
                    </div>
                @endif

                @if ($midtransStatus !== '')
                    <div class="meta-item">
                        <strong>Status dari Midtrans</strong>
                        <code>{{ $midtransStatus }}</code>
                    </div>
                @endif

                @if ($statusCode !== '')
                    <div class="meta-item">
                        <strong>Status Code</strong>
                        <code>{{ $statusCode }}</code>
                    </div>
                @endif

                @if ($paymentType !== '')
                    <div class="meta-item">
                        <strong>Payment Type</strong>
                        <code>{{ $paymentType }}</code>
                    </div>
                @endif

                @if ($fraudStatus !== '')
                    <div class="meta-item">
                        <strong>Fraud Status</strong>
                        <code>{{ $fraudStatus }}</code>
                    </div>
                @endif

                @if (! $transaction)
                    <div class="meta-item">
                        <strong>Status Halaman</strong>
                        <code>Detail transaksi lokal belum ditemukan. Halaman tetap dapat dipakai untuk menampilkan status dari parameter redirect.</code>
                    </div>
                @endif

                <div class="meta-item">
                    <strong>Finish Redirect URL</strong>
                    <code>{{ url('/midtrans/finish') }}</code>
                </div>
            </div>

            <div class="actions">
                <a class="button button-primary" href="{{ route('home') }}">Kembali ke Beranda</a>
                <a class="button" href="{{ route('docs.api') }}">Lihat Dokumentasi API</a>
            </div>
        </main>
    </body>
</html>
