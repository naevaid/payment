<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard - {{ config('app.name', 'payment') }}</title>
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
                padding: 28px 0 40px;
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 28px;
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
                margin-top: 2px;
                color: var(--text-muted);
                font-size: 13px;
            }

            .actions {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0 18px;
                border-radius: 12px;
                border: 1px solid var(--border);
                background: #fff;
                color: inherit;
                text-decoration: none;
                font-weight: 600;
                cursor: pointer;
            }

            .button-primary {
                border-color: var(--primary);
                background: var(--primary);
                color: #fff;
                box-shadow: 0 12px 28px rgba(29, 78, 216, 0.22);
            }

            .hero {
                display: grid;
                grid-template-columns: 1.2fr 0.8fr;
                gap: 24px;
                margin-bottom: 24px;
            }

            .panel {
                background: rgba(255, 255, 255, 0.94);
                border: 1px solid rgba(219, 226, 240, 0.95);
                border-radius: 28px;
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }

            .panel-body {
                padding: 32px;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                width: fit-content;
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
                font-size: clamp(32px, 5vw, 52px);
                line-height: 1.05;
            }

            .lead,
            .muted {
                margin: 0;
                color: var(--text-muted);
                line-height: 1.7;
            }

            .stats,
            .checklist {
                display: grid;
                gap: 14px;
                margin-top: 24px;
            }

            .stat,
            .item {
                padding: 18px;
                border-radius: 18px;
                border: 1px solid #e2e8f0;
                background: #f8fafc;
            }

            .stat strong,
            .item strong {
                display: block;
                margin-bottom: 6px;
                font-size: 14px;
            }

            .stat span,
            .item span {
                display: block;
                color: var(--text-muted);
                line-height: 1.6;
                font-size: 14px;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 24px;
            }

            .user-meta {
                display: grid;
                gap: 12px;
            }

            .meta {
                padding: 16px;
                border-radius: 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
            }

            .meta strong {
                display: block;
                margin-bottom: 6px;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: var(--text-muted);
            }

            .meta span {
                font-size: 16px;
                font-weight: 700;
            }

            @media (max-width: 900px) {
                .hero,
                .grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .shell {
                    width: min(100% - 24px, 1120px);
                }

                .topbar {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .actions {
                    width: 100%;
                }

                .actions .button,
                .actions form {
                    width: 100%;
                }

                .actions form .button {
                    width: 100%;
                }

                .panel-body {
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

                <div class="actions">
                    <a class="button" href="{{ route('home') }}">Lihat halaman publik</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button button-primary" type="submit">Logout</button>
                    </form>
                </div>
            </header>

            <section class="hero">
                <div class="panel">
                    <div class="panel-body">
                        <div class="eyebrow">Dashboard Utama</div>
                        <h1>Selamat datang, {{ auth()->user()->name }}.</h1>
                        <p class="lead">
                            Dashboard utama payment service sudah aktif. Ini menjadi titik awal untuk mengelola user
                            internal, tenant project, transaksi, Midtrans callback, dan monitoring forwarding callback.
                        </p>

                        <div class="stats">
                            <div class="stat">
                                <strong>Auth Web</strong>
                                <span>Login, register, logout, dan reset password sudah berjalan di project Laravel ini.</span>
                            </div>
                            <div class="stat">
                                <strong>Email</strong>
                                <span>SMTP bisa dipakai untuk welcome notification dan lupa password di environment production.</span>
                            </div>
                            <div class="stat">
                                <strong>Payment Core</strong>
                                <span>Fondasi `projects`, `transactions`, webhook Midtrans, dan callback forwarding sudah tersimpan di database.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="panel">
                    <div class="panel-body">
                        <div class="eyebrow">Akun Aktif</div>
                        <div class="user-meta" style="margin-top: 20px;">
                            <div class="meta">
                                <strong>Nama</strong>
                                <span>{{ auth()->user()->name }}</span>
                            </div>
                            <div class="meta">
                                <strong>Email</strong>
                                <span>{{ auth()->user()->email }}</span>
                            </div>
                            <div class="meta">
                                <strong>Environment</strong>
                                <span>{{ app()->environment() }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="grid">
                <div class="panel">
                    <div class="panel-body">
                        <div class="eyebrow">Next Step</div>
                        <div class="checklist">
                            <div class="item">
                                <strong>Projects / Tenants</strong>
                                <span>Lanjutkan CRUD tenant agar callback URL dan credential app bisa dikelola dari dashboard.</span>
                            </div>
                            <div class="item">
                                <strong>Transactions</strong>
                                <span>Tambahkan tabel listing transaksi, detail charge, dan status callback forwarding.</span>
                            </div>
                            <div class="item">
                                <strong>Operational Logs</strong>
                                <span>Tampilkan webhook log dan callback forwarding log untuk troubleshooting internal.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-body">
                        <div class="eyebrow">Gateway</div>
                        <div class="checklist">
                            <div class="item">
                                <strong>Midtrans Charge</strong>
                                <span>Endpoint charge API v1 sudah siap menerima request dari project internal.</span>
                            </div>
                            <div class="item">
                                <strong>Midtrans Webhook</strong>
                                <span>Signature webhook diverifikasi sebelum status transaksi diproses lebih lanjut.</span>
                            </div>
                            <div class="item">
                                <strong>Callback Forwarding</strong>
                                <span>Job forwarding callback dan retry log sudah tersedia untuk dipantau dari dashboard berikutnya.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-body">
                        <div class="eyebrow">Operasional</div>
                        <div class="checklist">
                            <div class="item">
                                <strong>Health Check</strong>
                                <span>Endpoint `/healthz` tetap aktif agar monitoring server tidak berubah.</span>
                            </div>
                            <div class="item">
                                <strong>Mailer</strong>
                                <span>SMTP production bisa dipakai untuk alur register dan lupa password.</span>
                            </div>
                            <div class="item">
                                <strong>Queue</strong>
                                <span>Redis dan worker khusus payment masih menjadi langkah lanjutan yang direkomendasikan.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
