<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') - {{ config('app.name', 'payment') }}</title>
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
                --danger-bg: #fef2f2;
                --danger-border: #fecaca;
                --danger-text: #b91c1c;
                --success-bg: #ecfdf5;
                --success-border: #a7f3d0;
                --success-text: #047857;
                --border: #dbe2f0;
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
                background:
                    radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 30%),
                    linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
                color: var(--text);
            }

            .shell {
                width: min(1120px, 100%);
                display: grid;
                grid-template-columns: 1fr 520px;
                gap: 28px;
                align-items: stretch;
            }

            .hero,
            .card {
                background: rgba(255, 255, 255, 0.94);
                border: 1px solid rgba(219, 226, 240, 0.95);
                border-radius: 28px;
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }

            .hero {
                padding: 36px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
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

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                width: fit-content;
                margin-top: 28px;
                padding: 8px 12px;
                border-radius: 999px;
                background: var(--surface-muted);
                color: var(--primary-dark);
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }

            .hero h1 {
                margin: 18px 0 12px;
                font-size: clamp(34px, 5vw, 54px);
                line-height: 1.05;
            }

            .hero p {
                margin: 0;
                color: var(--text-muted);
                font-size: 17px;
                line-height: 1.7;
            }

            .hero-points {
                display: grid;
                gap: 14px;
                margin-top: 24px;
            }

            .point {
                padding: 16px 18px;
                border-radius: 18px;
                border: 1px solid #e2e8f0;
                background: #f8fafc;
            }

            .point strong {
                display: block;
                margin-bottom: 6px;
                font-size: 14px;
            }

            .point span {
                display: block;
                color: var(--text-muted);
                line-height: 1.6;
                font-size: 14px;
            }

            .card {
                padding: 32px;
            }

            .card-header h2 {
                margin: 10px 0 10px;
                font-size: 31px;
            }

            .card-header p {
                margin: 0;
                color: var(--text-muted);
                line-height: 1.7;
            }

            .status,
            .errors {
                margin-top: 20px;
                padding: 14px 16px;
                border-radius: 16px;
                line-height: 1.6;
                font-size: 14px;
            }

            .status {
                border: 1px solid var(--success-border);
                background: var(--success-bg);
                color: var(--success-text);
            }

            .errors {
                border: 1px solid var(--danger-border);
                background: var(--danger-bg);
                color: var(--danger-text);
            }

            form {
                margin-top: 24px;
            }

            .field {
                margin-bottom: 18px;
            }

            label {
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
                font-weight: 600;
            }

            input {
                width: 100%;
                min-height: 46px;
                padding: 0 14px;
                border: 1px solid var(--border);
                border-radius: 14px;
                font: inherit;
                color: inherit;
                background: #fff;
            }

            input:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
            }

            .inline-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }

            .checkbox {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                color: var(--text-muted);
                font-size: 14px;
            }

            .checkbox input {
                width: 18px;
                min-height: 18px;
                height: 18px;
                margin: 0;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 46px;
                padding: 0 18px;
                border-radius: 14px;
                border: 1px solid var(--border);
                background: #fff;
                color: inherit;
                text-decoration: none;
                font-weight: 600;
                cursor: pointer;
                transition: 0.2s ease;
            }

            .button-primary {
                border-color: var(--primary);
                background: var(--primary);
                color: #fff;
                box-shadow: 0 12px 28px rgba(29, 78, 216, 0.22);
            }

            .button-primary:hover {
                border-color: var(--primary-dark);
                background: var(--primary-dark);
            }

            .button-secondary:hover {
                border-color: #cbd5e1;
                background: #fff;
            }

            .button-row {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 24px;
            }

            .muted-links {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 22px;
                color: var(--text-muted);
                font-size: 14px;
            }

            .muted-links a,
            .inline-row a {
                color: var(--primary-dark);
                text-decoration: none;
            }

            .muted-links a:hover,
            .inline-row a:hover {
                text-decoration: underline;
            }

            @media (max-width: 920px) {
                .shell {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                body {
                    padding: 16px;
                }

                .hero,
                .card {
                    padding: 24px;
                    border-radius: 22px;
                }

                .inline-row {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .button-row .button {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <aside class="hero">
                <div>
                    <a class="brand" href="{{ route('home') }}">
                        <span class="brand-badge">N</span>
                        <span class="brand-copy">
                            <strong>payment.naeva.id</strong>
                            <span>Centralized payment gateway service</span>
                        </span>
                    </a>

                    <div class="eyebrow">Naeva Internal Payments</div>
                    <h1>Dashboard utama untuk operasional payment service Naeva.</h1>
                    <p>
                        Flow login, register, notifikasi email, dan reset password sekarang disiapkan langsung di project
                        ini agar tim internal bisa mulai memakai dashboard utama tanpa panel terpisah.
                    </p>
                </div>

                <div class="hero-points">
                    <div class="point">
                        <strong>Auth Web Internal</strong>
                        <span>Login dan register memakai session Laravel bawaan agar sederhana dan aman untuk tahap awal.</span>
                    </div>
                    <div class="point">
                        <strong>Email Notification</strong>
                        <span>Welcome email dan forgot password memanfaatkan konfigurasi SMTP yang Anda siapkan.</span>
                    </div>
                    <div class="point">
                        <strong>Dashboard Utama</strong>
                        <span>Setelah login, user diarahkan ke dashboard utama untuk monitor fondasi payment service.</span>
                    </div>
                </div>
            </aside>

            <main class="card">
                <div class="card-header">
                    <div class="eyebrow">@yield('eyebrow')</div>
                    <h2>@yield('heading')</h2>
                    <p>@yield('subheading')</p>
                </div>

                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="errors">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </body>
</html>
