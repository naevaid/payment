<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'payment') }}</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f5f7fb;
                --surface: #ffffff;
                --surface-muted: #eef2ff;
                --text: #0f172a;
                --text-muted: #475569;
                --primary: #1d4ed8;
                --primary-dark: #1e40af;
                --border: #dbe2f0;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: Arial, Helvetica, sans-serif;
                background:
                    radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 30%),
                    linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
                color: var(--text);
            }

            .shell {
                width: min(1120px, calc(100% - 32px));
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
                text-decoration: none;
                font-weight: 600;
                transition: 0.2s ease;
            }

            .button-secondary {
                background: rgba(255, 255, 255, 0.7);
                color: var(--text);
            }

            .button-secondary:hover {
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
                grid-template-columns: 1.2fr 0.8fr;
                gap: 28px;
                padding: 28px 0 48px;
                align-items: stretch;
            }

            .panel {
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid rgba(219, 226, 240, 0.95);
                border-radius: 28px;
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }

            .hero-copy {
                padding: 40px;
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
                margin: 20px 0 14px;
                font-size: clamp(34px, 6vw, 58px);
                line-height: 1.05;
            }

            .lead {
                margin: 0;
                max-width: 680px;
                color: var(--text-muted);
                font-size: 18px;
                line-height: 1.7;
            }

            .hero-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 14px;
                margin-top: 28px;
            }

            .hero-notes {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 32px;
            }

            .note {
                padding: 16px;
                border-radius: 18px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
            }

            .note strong {
                display: block;
                margin-bottom: 6px;
                font-size: 14px;
            }

            .note span {
                color: var(--text-muted);
                font-size: 14px;
                line-height: 1.6;
            }

            .hero-status {
                padding: 28px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                background:
                    linear-gradient(180deg, rgba(29, 78, 216, 0.08) 0%, rgba(255, 255, 255, 0.95) 100%);
            }

            .status-card {
                padding: 22px;
                border-radius: 20px;
                background: #fff;
                border: 1px solid #e2e8f0;
                margin-bottom: 16px;
            }

            .status-card:last-child {
                margin-bottom: 0;
            }

            .status-label {
                color: var(--text-muted);
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                margin-bottom: 10px;
            }

            .status-value {
                font-size: 24px;
                font-weight: 700;
                margin: 0 0 8px;
            }

            .status-text {
                color: var(--text-muted);
                line-height: 1.6;
                margin: 0;
            }

            .footer-copy {
                padding: 0 0 40px;
                color: var(--text-muted);
                font-size: 14px;
            }

            @media (max-width: 900px) {
                .hero {
                    grid-template-columns: 1fr;
                }

                .hero-notes {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .topbar {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .nav-actions,
                .hero-actions {
                    width: 100%;
                }

                .nav-actions .button,
                .hero-actions .button {
                    flex: 1 1 auto;
                }

                .hero-copy,
                .hero-status {
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
                    <a class="button button-secondary" href="{{ route('login') }}">Login</a>
                    <a class="button button-primary" href="{{ route('register') }}">Register</a>
                </nav>
            </header>

            <main class="hero">
                <section class="panel hero-copy">
                    <span class="eyebrow">Naeva Internal Payments</span>
                    <h1>Payment gateway terpusat untuk seluruh project internal Naeva.</h1>
                    <p class="lead">
                        Halaman publik dasar sudah aktif. Konten landing page lengkap akan menyusul, sementara akses
                        masuk untuk tim internal tersedia lewat tombol Login dan Register di atas.
                    </p>

                    <div class="hero-actions">
                        <a class="button button-primary" href="{{ route('login') }}">Login</a>
                        <a class="button button-secondary" href="{{ route('register') }}">Register</a>
                    </div>

                    <div class="hero-notes">
                        <div class="note">
                            <strong>Charge API</strong>
                            <span>Menjadi titik integrasi utama project internal ke Midtrans.</span>
                        </div>
                        <div class="note">
                            <strong>Webhook Forwarding</strong>
                            <span>Meneruskan status pembayaran ke callback project asal secara async.</span>
                        </div>
                        <div class="note">
                            <strong>Monitoring</strong>
                            <span>Fondasi transaksi, log webhook, dan log forwarding sudah disiapkan.</span>
                        </div>
                    </div>
                </section>

                <aside class="panel hero-status">
                    <div class="status-card">
                        <div class="status-label">Service</div>
                        <p class="status-value">payment.naeva.id</p>
                        <p class="status-text">Public homepage aktif dan siap dikembangkan menjadi landing page penuh.</p>
                    </div>

                    <div class="status-card">
                        <div class="status-label">Environment</div>
                        <p class="status-value">{{ app()->environment() }}</p>
                        <p class="status-text">Konfigurasi lokal saat ini diarahkan ke Midtrans sandbox untuk pengembangan aman.</p>
                    </div>

                    <div class="status-card">
                        <div class="status-label">Health Check</div>
                        <p class="status-value">/healthz</p>
                        <p class="status-text">Endpoint health check tetap dipertahankan agar integrasi server tidak berubah.</p>
                    </div>
                </aside>
            </main>

            <footer class="footer-copy">
                Internal service Naeva. Landing page publik masih tahap awal dan akan dilengkapi pada iterasi berikutnya.
            </footer>
        </div>
    </body>
</html>
