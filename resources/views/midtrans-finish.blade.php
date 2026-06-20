<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Status Transaksi - {{ config('app.name', 'payment') }}</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
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
                border-radius: 18px;
                line-height: 1.6;
            }

            .notice strong {
                font-weight: 700;
            }

            .notice-success {
                border: 1px solid var(--success-border);
                background: var(--success-bg);
                color: var(--success-text);
            }

            .notice-pending {
                border: 1px solid #fcd34d;
                background: #fffbeb;
                color: #b45309;
            }

            .notice-failed {
                border: 1px solid #fecaca;
                background: #fef2f2;
                color: #b91c1c;
            }

            .notice-neutral {
                border: 1px solid var(--border);
                background: #f8fafc;
                color: var(--text);
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
        @php
            $normalizedStatus = strtolower((string) ($transaction?->status->value ?? $midtransStatus ?: ''));
            $displayStatus = $normalizedStatus !== '' ? str_replace('_', ' ', $normalizedStatus) : 'menunggu pembaruan';
            $statusLabel = ucfirst($displayStatus);
            $statusTone = match ($normalizedStatus) {
                'settlement', 'capture', 'success' => 'success',
                'pending' => 'pending',
                'deny', 'cancel', 'expire', 'failure', 'failed' => 'failed',
                default => 'neutral',
            };
            $headline = match ($statusTone) {
                'success' => 'Transaksi Anda Berhasil',
                'pending' => 'Transaksi Anda Sedang Diproses',
                'failed' => 'Transaksi Belum Berhasil',
                default => 'Status Transaksi',
            };
            $summary = match ($statusTone) {
                'success' => 'Pembayaran sudah diterima. Simpan informasi transaksi ini bila Anda membutuhkannya kembali.',
                'pending' => 'Pembayaran Anda masih menunggu penyelesaian. Silakan cek kembali beberapa saat lagi.',
                'failed' => 'Pembayaran belum dapat diselesaikan. Silakan coba lagi atau hubungi pihak terkait bila diperlukan.',
                default => 'Informasi transaksi ditampilkan sesuai data yang tersedia saat ini.',
            };
            $amountValue = $transaction?->amount;
            if ($amountValue === null && $grossAmount !== '') {
                $amountValue = (int) round((float) $grossAmount);
            }
            $amountCurrency = $transaction?->currency ?? 'IDR';
            $amountText = $amountValue !== null
                ? 'Rp '.number_format((int) $amountValue, 0, ',', '.').' '.$amountCurrency
                : null;
            $transactionTimeText = $settlementTime !== '' ? $settlementTime : ($transactionTime !== '' ? $transactionTime : null);
        @endphp
        <main class="card">
            <span class="eyebrow">Status Pembayaran</span>
            <h1>{{ $headline }}</h1>
            <p>{{ $summary }}</p>

            <div class="notice notice-{{ $statusTone }}">
                <strong>Status saat ini:</strong> {{ $statusLabel }}
            </div>

            <div class="meta">
                @if ($transaction?->client_order_id || $orderId !== '')
                    <div class="meta-item">
                        <strong>Order ID</strong>
                        <code>{{ $transaction?->client_order_id ?? $orderId }}</code>
                    </div>
                @endif

                @if ($transaction?->gateway_order_id)
                    <div class="meta-item">
                        <strong>Referensi Transaksi</strong>
                        <code>{{ $transaction->gateway_order_id }}</code>
                    </div>
                @endif

                @if ($normalizedStatus !== '')
                    <div class="meta-item">
                        <strong>Status Pembayaran</strong>
                        <code>{{ $statusLabel }}</code>
                    </div>
                @endif

                @if ($amountText !== null)
                    <div class="meta-item">
                        <strong>Total Pembayaran</strong>
                        <code>{{ $amountText }}</code>
                    </div>
                @endif

                @if ($paymentType !== '')
                    <div class="meta-item">
                        <strong>Metode Pembayaran</strong>
                        <code>{{ ucfirst(str_replace('_', ' ', $paymentType)) }}</code>
                    </div>
                @endif

                @if ($transactionTimeText !== null)
                    <div class="meta-item">
                        <strong>Waktu Transaksi</strong>
                        <code>{{ $transactionTimeText }}</code>
                    </div>
                @endif

                @if ($statusCode !== '')
                    <div class="meta-item">
                        <strong>Status Code</strong>
                        <code>{{ $statusCode }}</code>
                    </div>
                @endif

                @if ($fraudStatus !== '')
                    <div class="meta-item">
                        <strong>Fraud Status</strong>
                        <code>{{ ucfirst(str_replace('_', ' ', $fraudStatus)) }}</code>
                    </div>
                @endif

                @if ($transaction?->updated_at)
                    <div class="meta-item">
                        <strong>Terakhir Diperbarui</strong>
                        <code>{{ $transaction->updated_at->format('d M Y H:i:s') }}</code>
                    </div>
                @endif
            </div>
        </main>
    </body>
</html>
